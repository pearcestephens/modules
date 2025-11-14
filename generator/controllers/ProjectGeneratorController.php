<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GeneratedProject;
use App\Models\ProjectBuild;
use App\Models\ProjectIdea;
use App\Support\Response;
use App\Support\Logger;

/**
 * ProjectGeneratorController
 *
 * Handles intelligent project generation with multi-AI review,
 * security scanning, and comprehensive audit trails.
 *
 * Integrates SuperOrchestrator engine for production-grade code generation.
 */
class ProjectGeneratorController
{
    private Logger $logger;
    private GeneratedProject $projectModel;
    private ProjectBuild $buildModel;
    private ProjectIdea $ideaModel;
    private string $orchestratorPath;
    private string $jobsQueuePath;
    private string $artifactsPath;

    public function __construct()
    {
        $this->logger = new Logger(__CLASS__);
        $this->projectModel = new GeneratedProject();
        $this->buildModel = new ProjectBuild();
        $this->ideaModel = new ProjectIdea();

        $this->orchestratorPath = realpath(__DIR__ . '/../../..') . '/mcp/orchestrator/SuperOrchestrator.php';
        $this->jobsQueuePath = realpath(__DIR__ . '/../../..') . '/mcp/tmp/cis-projects/queue';
        $this->artifactsPath = realpath(__DIR__ . '/../../..') . '/mcp/tmp/cis-projects/artifacts';

        $this->ensureDirectories();
    }

    /**
     * Display project generator dashboard
     */
    public function index(): void
    {
        $recent_builds = $this->buildModel->getRecent(10);
        $active_jobs = $this->getActiveJobs();
        $project_stats = $this->projectModel->getStats();

        include __DIR__ . '/../../resources/views/generator/index.php';
    }

    /**
     * Show single project details and build history
     */
    public function show(int $id): void
    {
        $project = $this->projectModel->find($id);
        if (!$project) {
            Response::error('Project not found', 404);
            return;
        }

        $builds = $this->buildModel->getByProject($id);
        $ideas = $this->ideaModel->getByProject($id);

        include __DIR__ . '/../../resources/views/generator/show.php';
    }

    /**
     * List all projects with pagination
     */
    public function list(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $per_page = 20;

        $projects = $this->projectModel->paginate($page, $per_page);
        $total = $this->projectModel->count();
        $total_pages = ceil($total / $per_page);

        include __DIR__ . '/../../resources/views/generator/list.php';
    }

    /**
     * Create new project generation job
     */
    public function generate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        $errors = $this->validateGenerateInput($input);
        if (!empty($errors)) {
            Response::error('Validation failed', 422, ['errors' => $errors]);
            return;
        }

        try {
            // Create project record
            $project_id = $this->projectModel->create([
                'name' => $input['name'],
                'description' => $input['description'],
                'blueprint' => $input['blueprint'] ?? 'minimal',
                'status' => 'queued',
                'created_by' => $_SESSION['user_id'] ?? 0,
            ]);

            // Create build record
            $build_id = $this->buildModel->create([
                'project_id' => $project_id,
                'prompt' => $input['prompt'],
                'context' => $input['context'] ?? '',
                'options' => json_encode([
                    'multi_ai' => (bool)($input['enable_multi_ai'] ?? false),
                    'polish' => (bool)($input['enable_polish'] ?? false),
                    'scan' => (bool)($input['enable_scan'] ?? false),
                ]),
                'status' => 'queued',
            ]);

            // Queue the job
            $execution_id = $this->queueJob($project_id, $build_id, $input);

            // Log activity
            $this->logger->info('Project generation queued', [
                'project_id' => $project_id,
                'build_id' => $build_id,
                'execution_id' => $execution_id,
            ]);

            Response::success('Project generation queued', [
                'project_id' => $project_id,
                'build_id' => $build_id,
                'execution_id' => $execution_id,
                'event_stream_url' => "/api/generator/stream/$execution_id",
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Project generation failed', [
                'error' => $e->getMessage(),
            ]);
            Response::error('Project generation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Stream events via SSE
     */
    public function stream(string $execution_id): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $event_file = $this->jobsQueuePath . "/events/$execution_id.jsonl";

        if (!file_exists($event_file)) {
            echo "data: {\"error\": \"Execution not found\"}\n\n";
            flush();
            return;
        }

        $last_pos = 0;
        $start_time = time();
        $timeout = 300; // 5 minutes

        while (true) {
            // Check timeout
            if (time() - $start_time > $timeout) {
                echo "data: {\"type\": \"timeout\"}\n\n";
                break;
            }

            // Read new events
            if (file_exists($event_file)) {
                $handle = fopen($event_file, 'r');
                if ($handle) {
                    fseek($handle, $last_pos);

                    while (($line = fgets($handle)) !== false) {
                        if (trim($line)) {
                            $event = json_decode($line, true);
                            echo "data: " . json_encode($event) . "\n\n";
                            flush();
                        }
                    }

                    $last_pos = ftell($handle);
                    fclose($handle);
                }
            }

            // Check if job is complete
            $status_file = $this->jobsQueuePath . "/status/$execution_id.json";
            if (file_exists($status_file)) {
                $status = json_decode(file_get_contents($status_file), true);
                if ($status['status'] === 'complete' || $status['status'] === 'failed') {
                    echo "data: {\"type\": \"complete\", \"status\": \"{$status['status']}\"}\n\n";
                    flush();
                    break;
                }
            }

            usleep(500000); // 500ms polling
        }

        exit;
    }

    /**
     * Get build status and progress
     */
    public function status(string $execution_id): void
    {
        $status_file = $this->jobsQueuePath . "/status/$execution_id.json";

        if (!file_exists($status_file)) {
            Response::error('Execution not found', 404);
            return;
        }

        $status = json_decode(file_get_contents($status_file), true);
        Response::success('Build status retrieved', $status);
    }

    /**
     * Download project artifacts as ZIP
     */
    public function download(string $execution_id): void
    {
        $zip_path = $this->artifactsPath . "/$execution_id/project.zip";

        if (!file_exists($zip_path)) {
            Response::error('Project ZIP not found', 404);
            return;
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="project_' . $execution_id . '.zip"');
        header('Content-Length: ' . filesize($zip_path));

        readfile($zip_path);
        exit;
    }

    /**
     * Generate project idea (AI-powered suggestions)
     */
    public function generateIdea(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['category'])) {
            Response::error('Category required', 422);
            return;
        }

        try {
            // Generate idea using AI
            $idea = $this->generateAIIdea($input['category']);

            // Store idea
            $idea_id = $this->ideaModel->create([
                'category' => $input['category'],
                'title' => $idea['title'],
                'description' => $idea['description'],
                'blueprint_suggestion' => $idea['blueprint'],
                'starter_prompt' => $idea['prompt'],
                'created_by' => $_SESSION['user_id'] ?? 0,
            ]);

            $this->logger->info('Project idea generated', [
                'idea_id' => $idea_id,
                'category' => $input['category'],
            ]);

            Response::success('Idea generated', array_merge($idea, ['idea_id' => $idea_id]));

        } catch (\Exception $e) {
            $this->logger->error('Idea generation failed', [
                'error' => $e->getMessage(),
            ]);
            Response::error('Idea generation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get build details with audit trail
     */
    public function buildDetails(int $build_id): void
    {
        $build = $this->buildModel->find($build_id);
        if (!$build) {
            Response::error('Build not found', 404);
            return;
        }

        $audit_file = $this->artifactsPath . '/' . $build['execution_id'] . '/audit.json';
        $audit = file_exists($audit_file) ? json_decode(file_get_contents($audit_file), true) : [];

        Response::success('Build details retrieved', [
            'build' => $build,
            'audit' => $audit,
        ]);
    }

    /**
     * List all project ideas by category
     */
    public function ideas(): void
    {
        $category = $_GET['category'] ?? null;

        if ($category) {
            $ideas = $this->ideaModel->getByCategory($category);
        } else {
            $ideas = $this->ideaModel->getAll();
        }

        include __DIR__ . '/../../resources/views/generator/ideas.php';
    }

    /**
     * Internal: Queue a generation job
     */
    private function queueJob(int $project_id, int $build_id, array $input): string
    {
        $execution_id = 'exec_' . bin2hex(random_bytes(16));

        $job = [
            'execution_id' => $execution_id,
            'project_id' => $project_id,
            'build_id' => $build_id,
            'prompt' => $input['prompt'],
            'context' => $input['context'] ?? '',
            'blueprint' => $input['blueprint'] ?? 'minimal',
            'options' => [
                'multi_ai' => (bool)($input['enable_multi_ai'] ?? false),
                'polish' => (bool)($input['enable_polish'] ?? false),
                'scan' => (bool)($input['enable_scan'] ?? false),
                'force_serial' => true,
            ],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $queue_file = $this->jobsQueuePath . "/queue/$execution_id.json";
        file_put_contents($queue_file, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Create status file
        $status_file = $this->jobsQueuePath . "/status/$execution_id.json";
        file_put_contents($status_file, json_encode([
            'execution_id' => $execution_id,
            'status' => 'queued',
            'created_at' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));

        // Create events file
        $events_dir = $this->jobsQueuePath . '/events';
        if (!is_dir($events_dir)) {
            mkdir($events_dir, 0755, true);
        }
        touch($this->jobsQueuePath . "/events/$execution_id.jsonl");

        return $execution_id;
    }

    /**
     * Internal: Get active jobs
     */
    private function getActiveJobs(): array
    {
        $jobs = [];
        $queue_dir = $this->jobsQueuePath . '/queue';

        if (!is_dir($queue_dir)) {
            return $jobs;
        }

        foreach (scandir($queue_dir) as $file) {
            if (strpos($file, '.json') === false) {
                continue;
            }

            $job_data = json_decode(file_get_contents("$queue_dir/$file"), true);
            $status_file = $this->jobsQueuePath . "/status/" . $job_data['execution_id'] . '.json';

            if (file_exists($status_file)) {
                $status = json_decode(file_get_contents($status_file), true);
                if (in_array($status['status'], ['queued', 'processing'])) {
                    $jobs[] = array_merge($job_data, $status);
                }
            }
        }

        return $jobs;
    }

    /**
     * Internal: Validate generate input
     */
    private function validateGenerateInput(?array $input): array
    {
        $errors = [];

        if (empty($input)) {
            $errors[] = 'Request body required';
            return $errors;
        }

        if (empty($input['name']) || strlen($input['name']) < 3) {
            $errors[] = 'Project name required (min 3 characters)';
        }

        if (empty($input['prompt']) || strlen($input['prompt']) < 10) {
            $errors[] = 'Prompt required (min 10 characters)';
        }

        if (!empty($input['blueprint']) && !in_array($input['blueprint'], ['minimal', 'full'])) {
            $errors[] = 'Invalid blueprint (minimal or full)';
        }

        return $errors;
    }

    /**
     * Internal: Generate AI idea
     */
    private function generateAIIdea(string $category): array
    {
        $ideas = [
            'ecommerce' => [
                'title' => 'Multi-Vendor Marketplace',
                'description' => 'Build a robust e-commerce platform with vendor dashboard, product management, and real-time inventory sync.',
                'blueprint' => 'full',
                'prompt' => 'Create a complete multi-vendor e-commerce system with vendor dashboard, product listings, cart, checkout, payment processing, and order management. Include role-based access control for vendors and admins.',
            ],
            'saas' => [
                'title' => 'Project Management Tool',
                'description' => 'Create a collaborative project management system with tasks, timelines, and team collaboration features.',
                'blueprint' => 'full',
                'prompt' => 'Build a SaaS project management application with team collaboration, task management, Gantt charts, time tracking, and real-time notifications. Include free tier and premium subscription plans.',
            ],
            'social' => [
                'title' => 'Community Platform',
                'description' => 'Develop a social network with user profiles, activity feeds, and community engagement features.',
                'blueprint' => 'full',
                'prompt' => 'Create a community social platform with user profiles, activity feeds, messaging, notifications, and community moderation tools. Include content filtering and user reputation system.',
            ],
            'content' => [
                'title' => 'Blog & Content Hub',
                'description' => 'Build a content management system for blogging, articles, and knowledge base.',
                'blueprint' => 'minimal',
                'prompt' => 'Develop a blog platform with article publishing, categories, tags, comments, search, and SEO optimization. Include admin dashboard for content management.',
            ],
            'api' => [
                'title' => 'REST API Service',
                'description' => 'Create a production-grade REST API with authentication, rate limiting, and comprehensive documentation.',
                'blueprint' => 'full',
                'prompt' => 'Build a REST API service with JWT authentication, rate limiting, API versioning, comprehensive documentation, and monitoring. Include webhook support and API key management.',
            ],
        ];

        return $ideas[$category] ?? $ideas['ecommerce'];
    }

    /**
     * Internal: Ensure directories exist
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            $this->jobsQueuePath . '/queue',
            $this->jobsQueuePath . '/status',
            $this->jobsQueuePath . '/events',
            $this->artifactsPath,
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}

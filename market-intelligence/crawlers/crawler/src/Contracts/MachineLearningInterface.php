<?php

declare(strict_types=1);
/**
 * Machine Learning Interface.
 *
 * Contract for ML/AI components in crawler system
 * Defines methods for pattern recognition, adaptive learning, and prediction
 *
 * @version 2.0.0 - Ultra-Sophisticated ML/AI Enhanced
 */

namespace CIS\SharedServices\Crawler\Contracts;

interface MachineLearningInterface
{
    /**
     * Train model with historical data
     * Enhanced with: Online learning, incremental training, model versioning.
     *
     * @param array $trainingData Training dataset (features, labels)
     * @param array $options      Training options (algorithm, hyperparameters)
     *
     * @return array Training results (accuracy, loss, model_id)
     */
    public function train(array $trainingData, array $options = []): array;

    /**
     * Make prediction based on trained model
     * Enhanced with: Ensemble predictions, confidence scores.
     *
     * @param array $features Input features
     * @param array $options  Prediction options (model_id, threshold)
     *
     * @return array Prediction (result, confidence, explanation)
     */
    public function predict(array $features, array $options = []): array;

    /**
     * Detect anomalies in behavior patterns
     * NEW: Isolation Forest + One-Class SVM for anomaly detection.
     *
     * @param array $data Input data points
     *
     * @return array Anomaly scores (point, score, is_anomaly)
     */
    public function detectAnomalies(array $data): array;

    /**
     * Cluster similar patterns
     * NEW: K-Means + DBSCAN + Hierarchical clustering.
     *
     * @param array $data    Input data points
     * @param array $options Clustering options (algorithm, num_clusters)
     *
     * @return array Clusters (cluster_id, centroid, members)
     */
    public function cluster(array $data, array $options = []): array;

    /**
     * Forecast future values (time-series prediction)
     * NEW: Prophet (Facebook) + ARIMA + LSTM for time-series forecasting.
     *
     * @param array $timeSeries Historical time-series data
     * @param int   $periods    Number of periods to forecast
     * @param array $options    Forecast options (algorithm, confidence_interval)
     *
     * @return array Forecast (predicted_values, confidence_intervals, trends)
     */
    public function forecast(array $timeSeries, int $periods, array $options = []): array;

    /**
     * Feature importance analysis
     * NEW: SHAP values + permutation importance.
     *
     * @param array $features Feature set
     * @param array $labels   Target labels
     *
     * @return array Feature importance scores (feature, importance, rank)
     */
    public function getFeatureImportance(array $features, array $labels): array;

    /**
     * Optimize hyperparameters
     * NEW: Bayesian optimization + Grid search + Random search.
     *
     * @param array $parameterSpace Parameter search space
     * @param array $trainingData   Training dataset
     *
     * @return array Optimal parameters (params, score, iterations)
     */
    public function optimizeHyperparameters(array $parameterSpace, array $trainingData): array;

    /**
     * Q-Learning reinforcement learning
     * NEW: Q-Learning for adaptive behavior optimization.
     *
     * @param array  $state     Current state
     * @param string $action    Action taken
     * @param float  $reward    Reward received
     * @param array  $nextState Next state after action
     */
    public function updateQLearning(array $state, string $action, float $reward, array $nextState): void;

    /**
     * Get optimal action based on Q-Learning
     * NEW: Epsilon-greedy exploration vs exploitation.
     *
     * @param array $state   Current state
     * @param float $epsilon Exploration rate (0.0 - 1.0)
     *
     * @return string Recommended action
     */
    public function getOptimalAction(array $state, float $epsilon = 0.1): string;

    /**
     * Pattern matching with similarity scoring
     * NEW: Cosine similarity + Jaccard index + Edit distance.
     *
     * @param array  $pattern    Pattern to match
     * @param array  $candidates Candidate patterns
     * @param string $algorithm  Similarity algorithm
     *
     * @return array Matches (candidate, score, rank)
     */
    public function matchPattern(array $pattern, array $candidates, string $algorithm = 'cosine'): array;

    /**
     * Adaptive rate limiting with ML prediction
     * NEW: Token bucket + Leaky bucket + ML-based rate prediction.
     *
     * @param string $domain  Target domain
     * @param array  $history Request history
     *
     * @return float Recommended requests per second
     */
    public function predictOptimalRate(string $domain, array $history): float;

    /**
     * Sentiment analysis for content understanding
     * NEW: VADER + TextBlob + Custom models.
     *
     * @param string $text Input text
     *
     * @return array Sentiment (score, label, confidence)
     */
    public function analyzeSentiment(string $text): array;

    /**
     * Named Entity Recognition (NER)
     * NEW: spaCy + Stanford NER integration.
     *
     * @param string $text Input text
     *
     * @return array Entities (text, type, confidence)
     */
    public function extractEntities(string $text): array;

    /**
     * Get model performance metrics
     * Enhanced with: Cross-validation, confusion matrix, ROC curves.
     *
     * @param string $modelId Model identifier
     *
     * @return array Metrics (accuracy, precision, recall, f1, auc)
     */
    public function getModelMetrics(string $modelId): array;

    /**
     * Update model with new feedback (online learning)
     * NEW: Incremental learning without full retraining.
     *
     * @param array $newData New training examples
     */
    public function updateModel(array $newData): void;

    /**
     * Export trained model
     * NEW: Model serialization for deployment.
     *
     * @param string $modelId Model identifier
     * @param string $format  Export format (pickle, onnx, pmml)
     *
     * @return string Model file path or serialized data
     */
    public function exportModel(string $modelId, string $format = 'pickle'): string;

    /**
     * Import trained model
     * NEW: Model deserialization.
     *
     * @param string $modelPath Model file path or serialized data
     * @param string $format    Model format (pickle, onnx, pmml)
     *
     * @return string Imported model ID
     */
    public function importModel(string $modelPath, string $format = 'pickle'): string;

    /**
     * Clear model cache and reset state.
     */
    public function reset(): void;
}

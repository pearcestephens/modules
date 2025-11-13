<?php
/**
 * JsonLogger writes structured JSON lines for ingestion.
 */
class JsonLogger
{
    private string $file;
    private string $channel;
    public function __construct(string $channel, ?string $file = null)
    {
        $this->channel = $channel;
        $this->file = $file ?: dirname(__DIR__,2).'/logs/store_reports_events.log';
        $dir = dirname($this->file);
        if (!is_dir($dir)) { @mkdir($dir,0775,true); }
    }
    public function write(string $level, string $event, array $data = []): void
    {
        global $SR_CORRELATION_ID;
        $payload = [
            'ts' => date('c'),
            'level' => $level,
            'channel' => $this->channel,
            'event' => $event,
            'correlation_id' => $SR_CORRELATION_ID ?? null,
            'data' => $data
        ];
        file_put_contents($this->file, json_encode($payload, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
    }
    public function info(string $event, array $data = []): void { $this->write('INFO',$event,$data); }
    public function error(string $event, array $data = []): void { $this->write('ERROR',$event,$data); }
}

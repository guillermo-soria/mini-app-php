<?php
namespace App\Infra;

class Logger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $date = date('Y-m-d H:i:s');
        $entry = "[$date][$level] $message\n";
        $result = file_put_contents($this->logFile, $entry, FILE_APPEND);
        if ($result === false) {
            error_log("Logger error: Failed to write to log file: {$this->logFile}");
        }
    }
}


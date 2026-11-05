<?php

namespace app\core\helpers;

class Processes
{
    private $command = '';
    private $pid = 0;

    public function getCountSameProcess(): ?int
    {
        if (!$this->isLinux()) {
            return null;
        }

        $command = $this->getCurrentCommand();

        if (!$command) {
            return null;
        }

        return $this->getCountOfProcessByCommand($command);
    }

    public function getWorkSecondsCurrentProcess(): ?int
    {
        $pid = $this->getMyPID();

        if (!$pid) {
            return null;
        }

        return $this->getWorkSeconds($pid);
    }

    public function killOldProcess(int $time = 10800): ?bool
    {
        if (!$this->isLinux()) {
            return null;
        }

        $command = $this->getCurrentCommand();

        if (!$command) {
            return null;
        }

        $all_process_ids = $this->executeCommand('pgrep -f ' . escapeshellarg($command));

        $status = false;

        foreach ($all_process_ids as $process_id) {
            if (!ctype_digit($process_id)) {
                continue;
            }

            $seconds = $this->getWorkSeconds((int)$process_id);

            if ($seconds >= $time) {
                $this->executeCommand('kill ' . escapeshellarg($process_id));
                $status = true;
            }
        }

        return $status;
    }

    protected function getWorkSeconds(int $pid): ?int
    {
        $output = $this->executeCommand("ps -p $pid -o etimes");

        return count($output) === 2 ? (int)$output[1] : null;
    }

    protected function isLinux(): bool
    {
        return mb_stripos(PHP_OS, 'Linux') !== false;
    }

    protected function getCurrentCommand(): ?string
    {
        if ($this->command !== '') {
            return $this->command;
        }

        $pid = $this->getMyPID();

        if (!$pid) {
            return null;
        }

        $output = $this->executeCommand("ps -p $pid -o command");

        $this->command = count($output) === 2 ? $this->sanitizeCommand($output[1]) : null;

        return $this->command;
    }

    /**
     * Allowlist: only characters expected in a `php ...` invocation of our own
     * scripts (letters, digits, spaces, path separators). Anything else is
     * rejected rather than passed on to a shell command.
     */
    protected function sanitizeCommand(string $command): ?string
    {
        $command = trim($command);

        return preg_match('/^[A-Za-z0-9 _\-.\/]+$/', $command) === 1 ? $command : null;
    }

    protected function getCountOfProcessByCommand(string $command): ?int
    {
        $all_process = $this->executeCommand('ps -e -o command | grep ' . escapeshellarg('^' . $command . '$'));

        return empty($all_process) ? null : count($all_process);
    }

    protected function getMyPID(): ?int
    {
        if ($this->pid) {
            return $this->pid;
        }

        $this->pid = getmypid() ?: null;

        return $this->pid;
    }

    protected function executeCommand(string $command): array
    {
        $output = [];

        exec($command, $output);

        return $output;
    }
}
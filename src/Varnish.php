<?php

namespace DeltaBlue\Varnish;

use Symfony\Component\Process\Process;

class Varnish
{
    /*
     * Known exec types
     */
    const EXEC_SOCKET = 'socket';
    const EXEC_COMMAND = 'command';

    /**
     * @param string|array $host
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function flush($host = null): bool
    {
        $config = config('varnish');

        $host = $this->getHosts($host);
        $expr = $this->generateBanExpr($host);

        // Default to execution_type command when the config parameter is not set
        switch ($config['execution_type'] ?? self::EXEC_COMMAND) {
            case self::EXEC_SOCKET:
                return $this->executeSocketCommand($expr);
            case self::EXEC_COMMAND:
                return $this->executeCommand($this->generateBanCommand($expr));
            default:
                throw new \Exception(sprintf(
                    'Unknown execution type: %s', $config['execution_type']
                ));
        }
    }

    /**
     * @param array|string $host
     *
     * @return array
     */
    protected function getHosts($host = null): array
    {
        $host = $host ?? config('varnish.host');

        if (! is_array($host)) {
            $host = [$host];
        }

        return $host;
    }

    /**
     * @param string $expr
     *
     * @return string
     */
    public function generateBanCommand($expr = ''): string
    {
        $config = config('varnish');

        return "sudo varnishadm -S {$config['administrative_secret']} -T 127.0.0.1:{$config['administrative_port']} '{$expr}'";
    }

    /**
     * @param array $hosts
     *
     * @return string
     */
    public function generateBanExpr(array $hosts): string
    {
        $hostsRegex = collect($hosts)
            ->map(function (string $host) {
                return "(^{$host}$)";
            })
            ->implode('|');

        return sprintf('ban req.http.host ~ %s', $hostsRegex);
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        $config = config('varnish');
        if (! $secret = $config['administrative_secret_string']) {
            $secret = '';
            if (file_exists($config['administrative_secret'])) {
                $secret = trim(file_get_contents($config['administrative_secret']));
            }
        }

        return $secret;
    }

    /**
     * @param string $command
     *
     * @return bool
     *
     * @throws \Exception When the command fails
     */
    protected function executeCommand(string $command): bool
    {
        $process = new Process($command);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @param string $command
     *
     * @return bool
     *
     * @throws \Exception When connection to socket or command failed
     */
    protected function executeSocketCommand(string $command): bool
    {
        $config = config('varnish');
        $socket = new VarnishSocket();

        try {
            if ($socket->connect(
                $config['administrative_host'],
                $config['administrative_port'],
                $this->getSecret()
            )) {
                $socket->command($command);
                $socket->quit();
            }
        } catch (\Exception $e) {
            return false;
        } finally {
            $socket->close();
        }

        return true;
    }
}

<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace MuckiLogPlugin\Commands;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use MuckiLogPlugin\Core\Defaults as PluginDefaults;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Services\Settings as PluginSettings;

class CheckupSwLogger extends Command
{
    public static $defaultName = 'muckiware:logger:check-sw';

    protected ?ContainerInterface $container = null;

    public function __construct(
        protected PluginSettings $pluginSettings,
        protected LoggerInterface $logger
    ) {
        parent::__construct(self::$defaultName);
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    public function configure(): void
    {
        $this->setDescription(
            'Tests logging via id="logger" injection (typical Shopware pattern) — verifies the CompilerPass routes calls through the MuckiLog decorator.'
        );
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Start SW-logger checkup (id="logger" injection)');
        $output->writeln('Logger class: '.get_class($this->logger));

        $this->writeTestLogEntries($output);

        $output->writeln('Done SW-logger checkup');

        return Command::SUCCESS;
    }

    private function writeTestLogEntries(OutputInterface $output): void
    {
        $output->writeln('Write into path: '.$this->pluginSettings->getLogPath());

        foreach (LogLevel::cases() as $key => $logLevel) {
            $method = $logLevel->value;

            $output->writeln($key.' - Write '.$method.' via id="logger"');

            // Einfache Nachricht — kein Context → Fallback sw/dev Log-Datei
            $this->logger->{$method}($key.' - SW-logger test (no context): '.$method);

            // Mit Vendor/Plugin-Context → eigene Log-Datei
            $this->logger->{$method}(
                $key.' - SW-logger test (with context): '.$method,
                [PluginDefaults::CONTEXT, PluginDefaults::EXTENSION]
            );
        }

        $defaultLogFile = $this->pluginSettings->getLogPath().'/'.PluginDefaults::DEFAULT_SW_EXTENSION.'.'.PluginDefaults::DEFAULT_SW_CONTEXT.'.log';
        $contextLogFile = $this->pluginSettings->getLogPath().'/'.PluginDefaults::EXTENSION.'.'.PluginDefaults::CONTEXT.'.log';

        if (file_exists($defaultLogFile)) {
            $output->writeln('OK: default log file written → '.$defaultLogFile);
        } else {
            $output->writeln('FAIL: default log file missing → '.$defaultLogFile);
        }

        if (file_exists($contextLogFile)) {
            $output->writeln('OK: context log file written → '.$contextLogFile);
        } else {
            $output->writeln('FAIL: context log file missing → '.$contextLogFile);
        }
    }
}

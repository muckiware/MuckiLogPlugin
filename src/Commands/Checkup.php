<?php
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Commands;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

use MuckiLogPlugin\Core\Defaults as PluginDefaults;
use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLoggerInterface;
use MuckiLogPlugin\Services\PsrLoggerServiceDecorator;

class Checkup extends Command
{
    protected LogconfigInterface $logconfig;
    protected MuckiLoggerInterface $muckilogLogger;
    /**
     * @var string
     */
    public static $defaultName = 'muckiware:logger:check';

    protected ?ContainerInterface $container = null;

    public function __construct(
        protected PluginSettings $pluginSettings,
        protected LogconfigInterface $logconfigInterface,
        protected LoggerInterface $psrLogger,
        protected LoggerInterface $monoLogger,
        protected MuckiLoggerInterface $muckiLogger,
        protected PsrLoggerServiceDecorator $loggerServiceDecorator
    )
    {
        parent::__construct(self::$defaultName);
        $this->logconfig = $logconfigInterface;
        $this->muckilogLogger = $muckiLogger;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * @internal
     */
    public function configure(): void
    {
        $this->setDescription('This Muckilog plugin command is just for testing logging method.');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Start muckilog checkup');
        $output->writeln('Plugin is run in '.$this->pluginSettings->getPluginInstallPath());

        $this->removeOldFiles($output);
        $this->writeTestLogFiles($output);

        $output->writeln('Done muckilog checkup');

        return 0;
    }

    protected function removeOldFiles(OutputInterface $output): void
    {
        $output->writeln('Remove log config files from '.$this->pluginSettings->getLogConfigPath());
        $this->logconfig->removeLogConfigFiles(
            $this->pluginSettings->getLogConfigPath()
        );

        $output->writeln('Remove old log files');
        if(file_exists($this->pluginSettings->getLogPath().'/muckilog.log')) {
            unlink($this->pluginSettings->getLogPath().'/muckilog.log');
        }
        if(file_exists($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION . '.' . PluginDefaults::CONTEXT . '.log')) {
            unlink($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION . '.' . PluginDefaults::CONTEXT . '.log');
        }
        if(file_exists($this->pluginSettings->getLogPath().'/'.PluginDefaults::DEFAULT_SW_EXTENSION.'.'.PluginDefaults::DEFAULT_SW_CONTEXT.'.log')) {
            unlink($this->pluginSettings->getLogPath().'/'.PluginDefaults::DEFAULT_SW_EXTENSION.'.'.PluginDefaults::DEFAULT_SW_CONTEXT.'.log');
        }
        if(file_exists($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION_SW . '.' . PluginDefaults::CONTEXT . '.log')) {
            unlink($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION_SW . '.' . PluginDefaults::CONTEXT . '.log');
        }
    }

    protected function writeTestLogFiles(OutputInterface $output): void
    {
        $output->writeln('Write into path: '.$this->pluginSettings->getLogPath());

        foreach (LogLevel::cases() as $key => $loggingMethod) {

            $output->writeln($key.' - Write '.$loggingMethod->value.'. Default muckilog');

            $this->psrLogger->{$loggingMethod->value}($key.' - Test log item for -> '.$loggingMethod->value);
            $this->psrLogger->{$loggingMethod->value}([$key.' - Test log array for -> '.$loggingMethod->value]);

            $this->monoLogger->{$loggingMethod->value}($key.' - Test log item for -> '.$loggingMethod->value);
            $this->monoLogger->{$loggingMethod->value}([$key.' - Test log array for -> '.$loggingMethod->value]);

            $output->writeln(
                $key.' - Write '.$loggingMethod->value.'. With context: '. PluginDefaults::CONTEXT.' extension '.PluginDefaults::EXTENSION
            );
            $this->psrLogger->{$loggingMethod->value}(
                $key.' - Test log item for -> '.$loggingMethod->value, array(PluginDefaults::CONTEXT, PluginDefaults::EXTENSION)
            );
            $this->psrLogger->{$loggingMethod->value}(
                $key.' - Test log item for -> '.$loggingMethod->value, array(
                    PluginDefaults::CONTEXT,
                    PluginDefaults::EXTENSION,
                    array('setup' => array(
                        'notificationEmail' => true,
                        'notificationEmailReceiver' => 'notificationEmailReceiver@example.com',
                        'notificationEmailSender' => 'notificationEmailSender@example.com',
                    ))
                )
            );

            $this->monoLogger->{$loggingMethod->value}(
                $key.' - Test log item for -> '.$loggingMethod->value, array(PluginDefaults::CONTEXT, PluginDefaults::EXTENSION)
            );
            $this->monoLogger->{$loggingMethod->value}(
                $key.' - Test log item for -> '.$loggingMethod->value, array(
                    PluginDefaults::CONTEXT,
                    PluginDefaults::EXTENSION,
                    array('setup' => array(
                        'notificationEmail' => true,
                        'notificationEmailReceiver' => 'notificationEmailReceiver@example.com',
                        'notificationEmailSender' => 'notificationEmailSender@example.com',
                    ))
                )
            );
        }

        if(file_exists($this->pluginSettings->getLogPath().'/muckilog.log')) {
            $output->writeln('Write '.$this->pluginSettings->getLogPath().'/muckilog.log'.' seems okay');
        }
        if(file_exists($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION . '.' . PluginDefaults::CONTEXT . '.log')) {
            $output->writeln('Write '.$this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION . '.' . PluginDefaults::CONTEXT . '.log'.' seems okay');
        }
    }
}

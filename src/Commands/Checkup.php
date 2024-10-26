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
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLoggerInterface;
use MuckiLogPlugin\Services\LoggerServiceDecorator;

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
        protected LoggerInterface $logger,
        protected MuckiLoggerInterface $muckiLogger,
        protected LoggerServiceDecorator $loggerServiceDecorator
    )
    {
        parent::__construct(self::$defaultName);
        $this->logconfig = $logconfigInterface;
        $this->muckilogLogger = $muckiLogger;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @internal
     */
    public function configure()
    {
        $this->setDescription('This command is just for testing logging method.');
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
        $this->writeTestLogFiles($output, true);
        $this->writeTestLogFiles($output, false);

        $output->writeln('Done muckilog checkup');

        return 0;
    }

    protected function removeOldFiles($output)
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
        if(file_exists($this->pluginSettings->getLogPath().'/'.$this->loggerServiceDecorator::DEFAULT_SW_EXTENSION.'.'.$this->loggerServiceDecorator::DEFAULT_SW_CONTEXT.'.log')) {
            unlink($this->pluginSettings->getLogPath().'/'.$this->loggerServiceDecorator::DEFAULT_SW_EXTENSION.'.'.$this->loggerServiceDecorator::DEFAULT_SW_CONTEXT.'.log');
        }
        if(file_exists($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION_SW . '.' . PluginDefaults::CONTEXT . '.log')) {
            unlink($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION_SW . '.' . PluginDefaults::CONTEXT . '.log');
        }
    }

    protected function writeTestLogFiles($output, $useMuckilogger = true)
    {
        $output->writeln('Write into path: '.$this->pluginSettings->getLogPath());

        if($useMuckilogger) {

            $loggingMethods = get_class_methods($this->muckilogLogger);
            foreach ($loggingMethods as $key => $loggingMethod) {

                if(
                    $loggingMethod !== '__construct' &&
                    $loggingMethod !== 'executeLoggingByLogLevel' &&
                    $loggingMethod !== 'inputMessageFilter'
                ) {

                    $output->writeln($key.' - Write '.$loggingMethod.'. Default muckilog');
                    $this->muckilogLogger->{$loggingMethod}($key.' - Test log item for -> '.$loggingMethod);
                    $this->muckilogLogger->{$loggingMethod}([$key.' - Test log array for -> '.$loggingMethod]);

                    $output->writeln(
                        $key.' - Write '.$loggingMethod.'. With context: '. PluginDefaults::CONTEXT.' extension '.PluginDefaults::EXTENSION
                    );
                    $this->muckilogLogger->{$loggingMethod}(
                        $key.' - Test log item for -> '.$loggingMethod, PluginDefaults::CONTEXT, PluginDefaults::EXTENSION
                    );
                }
            }

            if(file_exists($this->pluginSettings->getLogPath().'/muckilog.log')) {
                $output->writeln('Write '.$this->pluginSettings->getLogPath().'/muckilog.log'.' seems okay');
            }
            if(file_exists($this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION . '.' . PluginDefaults::CONTEXT . '.log')) {
                $output->writeln('Write '.$this->pluginSettings->getLogPath() . '/' . PluginDefaults::EXTENSION . '.' . PluginDefaults::CONTEXT . '.log'.' seems okay');
            }
        } else {

            $loggingMethods = get_class_methods($this->logger);

            foreach ($loggingMethods as $key => $loggingMethod) {

                if($loggingMethod !== '__construct') {

                    if($loggingMethod !== 'log') {

                        $output->writeln($key . ' - Write ' . $loggingMethod . '. Default shopware log');
                        $this->logger->{$loggingMethod}($key . ' - Test log item for -> ' . $loggingMethod);

                        $output->writeln($key . ' - Write ' . $loggingMethod . '. With context: ' . PluginDefaults::CONTEXT . ' extension ' . PluginDefaults::EXTENSION_SW);
                        $this->logger->{$loggingMethod}($key . ' - Test log item for -> ' . $loggingMethod, array(PluginDefaults::CONTEXT, PluginDefaults::EXTENSION_SW));

                    } else {

                        foreach ($loggingMethods as $key => $loggingLevel) {

                            if($loggingLevel !== '__construct') {

                                $output->writeln($key . ' - Write with level ' . $loggingLevel . '. Default shopware log');
                                $this->logger->log($loggingLevel, $key . ' - Test log item with log parameter -> ' . $loggingLevel);

                                $output->writeln($key . ' - Write with level ' . $loggingLevel . '. With context: ' . PluginDefaults::CONTEXT . ' extension ' . PluginDefaults::EXTENSION_SW);
                                $this->logger->log($loggingLevel, $key . ' - Test log item with log parameter -> ' . $loggingLevel, array(PluginDefaults::CONTEXT, PluginDefaults::EXTENSION_SW));
                            }
                        }
                    }
                }
            }
        }
    }
}

<?php
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021 by Muckiware
 *
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
use Shopware\Core\Framework\Context;
use MuckiLogPlugin\Services\SettingsInterface;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLoggerInterface;
use MuckiLogPlugin\Services\LoggerServiceDecorator;

class Checkup extends Command {

    const CONTEXT = 'acme';
    const EXTENSION = 'loggerCheck';
    const EXTENSION_SW = 'loggerCheckSw';

    protected Importer $importer;

    protected SettingsInterface $_settings;

    protected LogconfigInterface $_logconfig;

    protected LoggerInterface $logger;

    protected MuckiLoggerInterface $muckilogLogger;

    protected LoggerServiceDecorator $loggerServiceDecorator;

    /**
     * @var string
     */
    public static $defaultName = 'muckiware:logger:check';

    protected ?ContainerInterface $container = null;

    public function __construct(
        SettingsInterface $settingsInterface,
        LogconfigInterface $logconfigInterface,
        LoggerInterface $logger,
        MuckiLoggerInterface $muckiLogger,
        LoggerServiceDecorator $loggerServiceDecorator
    ) {

        parent::__construct(self::$defaultName);
        $this->_settings = $settingsInterface;
        $this->_logconfig = $logconfigInterface;
        $this->muckilogLogger = $muckiLogger;
        $this->logger = $logger;
        $this->loggerServiceDecorator = $loggerServiceDecorator;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface {
        return $this->container;
    }

    /**
     * @internal
     */
    public function configure() {

        $this->setDescription('This command is just for testing logging method.');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output) {

        $output->writeln('Start muckilog checkup');

        $this->removeOldFiles($output);
        $this->writeTestLogFiles($output, true);
        $this->writeTestLogFiles($output, false);

        $output->writeln('Done muckilog checkup');

        return 0;
    }

    protected function removeOldFiles($output) {

        $output->writeln('Remove log config files from '.$this->_settings->getLogConfigPath());
        $this->_logconfig->removeLogConfigFiles(
            $this->_settings->getLogConfigPath()
        );

        $output->writeln('Remove old log files');
        if(file_exists($this->_settings->getLogPath().'/muckilog.log')) {
            unlink($this->_settings->getLogPath().'/muckilog.log');
        }
        if(file_exists($this->_settings->getLogPath() . '/' . self::EXTENSION . '.' . self::CONTEXT . '.log')) {
            unlink($this->_settings->getLogPath() . '/' . self::EXTENSION . '.' . self::CONTEXT . '.log');
        }
        if(file_exists($this->_settings->getLogPath().'/'.$this->loggerServiceDecorator::DEFAULT_SW_EXTENSION.'.'.$this->loggerServiceDecorator::DEFAULT_SW_CONTEXT.'.log')) {
            unlink($this->_settings->getLogPath().'/'.$this->loggerServiceDecorator::DEFAULT_SW_EXTENSION.'.'.$this->loggerServiceDecorator::DEFAULT_SW_CONTEXT.'.log');
        }
        if(file_exists($this->_settings->getLogPath() . '/' . self::EXTENSION_SW . '.' . self::CONTEXT . '.log')) {
            unlink($this->_settings->getLogPath() . '/' . self::EXTENSION_SW . '.' . self::CONTEXT . '.log');
        }
    }

    protected function writeTestLogFiles($output, $useMuckilogger = true) {

        $output->writeln('Write into path: '.$this->_settings->getLogPath());

        if($useMuckilogger) {

            $loggingMethods = get_class_methods($this->muckilogLogger);
            foreach ($loggingMethods as $key => $loggingMethod) {

                if($loggingMethod !== '__construct') {

                    $output->writeln($key.' - Write '.$loggingMethod.'. Default muckilog');
                    $this->muckilogLogger->{$loggingMethod}($key.' - Test log item for -> '.$loggingMethod);

                    $output->writeln($key.' - Write '.$loggingMethod.'. With context: '. self::CONTEXT.' extension '.self::EXTENSION);
                    $this->muckilogLogger->{$loggingMethod}($key.' - Test log item for -> '.$loggingMethod, self::CONTEXT, self::EXTENSION);
                }
            }

            if(file_exists($this->_settings->getLogPath().'/muckilog.log')) {
                $output->writeln('Write '.$this->_settings->getLogPath().'/muckilog.log'.' seems okay');
            }
            if(file_exists($this->_settings->getLogPath() . '/' . self::EXTENSION . '.' . self::CONTEXT . '.log')) {
                $output->writeln('Write '.$this->_settings->getLogPath() . '/' . self::EXTENSION . '.' . self::CONTEXT . '.log'.' seems okay');
            }
        } else {

            $loggingMethods = get_class_methods($this->logger);

            foreach ($loggingMethods as $key => $loggingMethod) {

                if($loggingMethod !== '__construct') {

                    if($loggingMethod !== 'log') {

                        $output->writeln($key . ' - Write ' . $loggingMethod . '. Default shopware log');
                        $this->logger->{$loggingMethod}($key . ' - Test log item for -> ' . $loggingMethod);

                        $output->writeln($key . ' - Write ' . $loggingMethod . '. With context: ' . self::CONTEXT . ' extension ' . self::EXTENSION_SW);
                        $this->logger->{$loggingMethod}($key . ' - Test log item for -> ' . $loggingMethod, array(self::CONTEXT, self::EXTENSION_SW));

                    } else {

                        foreach ($loggingMethods as $key => $loggingLevel) {

                            if($loggingLevel !== '__construct') {

                                $output->writeln($key . ' - Write with level ' . $loggingLevel . '. Default shopware log');
                                $this->logger->log($loggingLevel, $key . ' - Test log item with log parameter -> ' . $loggingLevel);

                                $output->writeln($key . ' - Write with level ' . $loggingLevel . '. With context: ' . self::CONTEXT . ' extension ' . self::EXTENSION_SW);
                                $this->logger->log($loggingLevel, $key . ' - Test log item with log parameter -> ' . $loggingLevel, array(self::CONTEXT, self::EXTENSION_SW));
                            }
                        }
                    }
                }
            }
        }
    }
}

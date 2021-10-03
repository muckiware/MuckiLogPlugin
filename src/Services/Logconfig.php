<?php declare(strict_types=1);
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

namespace MuckiLogPlugin\Services;

use Symfony\Component\HttpKernel\KernelInterface;
use MuckiLogPlugin\Services\SettingsInterface;
use MuckiLogPlugin\log4php\Logger;

class Logconfig implements LogconfigInterface {

    /**
     * 
     * @var KernelInterface
     */
    protected KernelInterface $kernel;
    
    /**
     * 
     * @var SettingsInterface
     */
    protected SettingsInterface $_settings;
    
    /**
     * 
     * @var Logger
     */
    protected $_logger;
    
    public function __construct(
        KernelInterface $kernel,
        SettingsInterface $settings
    ) {
        $this->kernel = $kernel;
        $this->_settings = $settings;
    }
    
    public function  setSettings($settings) {
        $this->_settings = $settings;
    }
    
    public function getLogger() {
        
        if(!$this->_logger) {
            require_once $this->kernel->getProjectDir().Settings::LOGGER_PATH;
            $this->_logger = \MuckiLogPlugin\log4php\Logger::getLogger('muckilog');
        }

        return $this->_logger;
    }
    /**
     * Method for to load a xml config file, if it already exists.
     *
     * @param string $loggerContext
     * @return boolean
     */
    public function checkConfigPath($loggerContext = '', $extensionContext = ''): bool {

        $configFilePath = $this->_settings->getConfigPath($loggerContext, $extensionContext);
        
        if($this->_checkConfigFile($configFilePath, $loggerContext, $extensionContext)) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function _checkConfigFile($path, $loggerContext = '', $extensionContext = '') {
        
        if(file_exists($path)) {
            return true;
        } else {

            $this->_createConfigXML($path, $loggerContext, $extensionContext);
            
            if(file_exists($path)) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    /**
     * Method to create a new log4php xml config file
     * @param string $loggerContext
     * @return void
     */
    protected function _createConfigXML($configFilePath, $loggerContext = '', $extensionContext = '') {

        $dom = new \DomDocument('1.0', 'UTF-8');
        
        //add root
        $root = $dom->appendChild($dom->createElement('configuration'));
        
        //add NodeA element to Root
        $nodeAppender = $dom->createElement('appender');
        $nodeRoot = $dom->createElement('root');
        $root->appendChild($nodeAppender);
        $root->appendChild($nodeRoot);
        
        $nodeParam1 = $dom->createElement('param');
        $nodeAppender->appendChild($nodeParam1);
        $nodeParam2 = $dom->createElement('param');
        $nodeAppender->appendChild($nodeParam2);
        $nodeParam3 = $dom->createElement('param');
        $nodeAppender->appendChild($nodeParam3);
        $nodeLayout = $dom->createElement('layout');
        $nodeAppender->appendChild($nodeLayout);
        $nodeFilter = $dom->createElement('filter');
        $nodeAppender->appendChild($nodeFilter);
        
        $nodeLayoutParam = $dom->createElement('param');
        $nodeLayout->appendChild($nodeLayoutParam);
        $nodeFilterParam1 = $dom->createElement('param');
        $nodeFilter->appendChild($nodeFilterParam1);
        $nodeFilterParam2 = $dom->createElement('param');
        $nodeFilter->appendChild($nodeFilterParam2);
        
        $nodeLevel = $dom->createElement('level');
        $nodeRoot->appendChild($nodeLevel);
        $nodeAppenderRef = $dom->createElement('appender_ref');
        $nodeRoot->appendChild($nodeAppenderRef);
        
        // Appending attr1 and attr2 to the NodeA element
        $attr = $dom->createAttribute('xmlns');
        $attr->appendChild($dom->createTextNode('http://logging.apache.org/log4php/'));
        $root->appendChild($attr);
        
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('RollingFileAppender'));
        $nodeAppender->appendChild($attr);
        
        $attr = $dom->createAttribute('class');
        $attr->appendChild($dom->createTextNode('MuckiLogPlugin\log4php\appenders\LoggerAppenderRollingFile'));
        $nodeAppender->appendChild($attr);
        
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('file'));
        $nodeParam1->appendChild($attr);
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode(
            $this->_settings->getLoggerFileName(
                $loggerContext,
                $extensionContext
            )
        ));
        $nodeParam1->appendChild($attr);
        
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('maxBackupIndex'));
        $nodeParam2->appendChild($attr);
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode($this->_settings->getMaxBackupIndex()));
        $nodeParam2->appendChild($attr);
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('maxFileSize'));
        $nodeParam3->appendChild($attr);
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode($this->_settings->getMaxFileSize()));
        $nodeParam3->appendChild($attr);
        
        $attr = $dom->createAttribute('class');
        $attr->appendChild($dom->createTextNode('MuckiLogPlugin\log4php\layouts\LoggerLayoutPattern'));
        $nodeLayout->appendChild($attr);
        
        $attr = $dom->createAttribute('class');
        $attr->appendChild($dom->createTextNode('MuckiLogPlugin\log4php\filters\LoggerFilterLevelRange'));
        $nodeFilter->appendChild($attr);
        
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('conversionPattern'));
        $nodeLayoutParam->appendChild($attr);
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode($this->_settings->getConversionPattern()));
        $nodeLayoutParam->appendChild($attr);
        
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('levelMin'));
        $nodeFilterParam1->appendChild($attr);
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode($this->_settings->getLoglevel()));
        $nodeFilterParam1->appendChild($attr);
        
        $attr = $dom->createAttribute('name');
        $attr->appendChild($dom->createTextNode('acceptOnMatch'));
        $nodeFilterParam2->appendChild($attr);
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode('false'));
        $nodeFilterParam2->appendChild($attr);
        
        $attr = $dom->createAttribute('value');
        $attr->appendChild($dom->createTextNode('DEBUG'));
        $nodeLevel->appendChild($attr);
        
        $attr = $dom->createAttribute('ref');
        $attr->appendChild($dom->createTextNode('RollingFileAppender'));
        $nodeAppenderRef->appendChild($attr);
        
        $dom->formatOutput = true; // set the formatOutput attribute of domDocument to true
        
        // save XML as string or file
        try {
            $dom->save($configFilePath);
        } catch (\Exception $e) {
            echo 'Error: '.$e->getMessage();
        }
    }
    
    public function removeLogConfigFiles($path) {
        
        foreach(glob($path.'/logconfig.*') as $file) {
            if(is_file($file))
                unlink($file);
        }
    }
}


<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package Config Magic
 * @copyright Copyright (c) 2005 Alan Pinstein. All Rights Reserved.
 * @author Alan Pinstein <apinstein@mac.com>                        
 * 
 * Copyright (c) 2009 Alan Pinstein <apinstein@mac.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

class ConfigMagic
{
    const OPT_CONFIG_DIR                 = 'configDir';
    const OPT_VERBOSE                    = 'verbose';
    const OPT_QUIET                      = 'quiet';

    /**
     * @var string The path to the directory where migrations are stored.
     */
    protected $configDir;

    /**
     * Create a migrator instance.
     *
     * @param array Options Hash: set any of the ConfigMagic::OPT_* options.
     */
    public function __construct($opts = array())
    {
        $opts = array_merge(array(
                                ConfigMagic::OPT_CONFIG_DIR            => './config',
                                ConfigMagic::OPT_VERBOSE               => false,
                                ConfigMagic::OPT_QUIET                 => false,
                           ), $opts);

        // set up initial data
        $this->setConfigDirectory($opts[ConfigMagic::OPT_CONFIG_DIR]);
        $this->verbose = $opts[ConfigMagic::OPT_VERBOSE];
        $this->quiet = $opts[ConfigMagic::OPT_QUIET];

        // initialize migration state
        $this->logMessage("ConfigMagic - The PHP Configuration Organizer.\n");

        $this->initializeConfigDir();
        $this->readConfig();
    }

    protected function initializeConfigDir()
    {
        // initialize migrations dir
        $configDir = $this->getConfigDirectory();
        if (!file_exists($configDir))
        {
            $this->logMessage("Config directory does not exist.\nInitializing new config directory at {$configDir}.\n");

            mkdir($configDir, 0777, true);
            $cleanTPL = <<<END
; The "templates" directive is a special directive that lists all config templates managed by ConfigMagic
; There are a handful of tokens that you can use in your values to use dynamic data:
; ##CONFIG_DIR##    => Absolute path to the config directory. You can then use relative paths to precisely control input/output location for your config files.
; ##CONFIG##        => The current "config" name (ie dev/staging/production)
[templates]
template.webapp.input = ##CONFIG_DIR##/webapp.conf
template.webapp.output = ##CONFIG_DIR##/webapp-##CONFIG##.conf

END;
            file_put_contents($configDir . '/config.ini', $cleanTPL);
        }
    }

    public function setConfigDirectory($d)
    {
        $this->configDir = $d;
        return $this;
    }

    public function getConfigDirectory()
    {
        return $this->configDir;
    }

    public function logMessage($msg, $onlyIfVerbose = false)
    {
        if ($this->quiet) return;
        if (!$this->verbose && $onlyIfVerbose) return;
        print $msg;
    }

    protected function readConfig()
    {
        $iniFile = $this->getConfigDirectory() . '/config.ini';
        $config = parse_ini_file($iniFile);
        print_r($config);
    }
}

$c = new ConfigMagic();

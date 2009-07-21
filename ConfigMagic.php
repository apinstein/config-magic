<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package ConfigMagic
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

/**
 * ConfigMagic combines a set of configFileTemplates with a set of configFileData for a given profile and produces a set of output config files that have the configFileData applied to the configFileTemplates.
 *
 * NOMENCLATURE:
 * profile              => The name of the "profile" for a set of config files. Example: dev, staging, production
 * config               => The "conceptual" idea of a conf file; a conveient alias for that file. Example: "httpd.conf"
 * profileData          => An ini file containing a set of name-value pairs to be for a given profile that will be applied to the config file templates. Example: dev.ini
 * configFileTemplate   => The template file for the corresponding configFile. Example: http.conf
 * configFile           => A completed config file, ready for use. Example: httpd-production.conf
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
     * @var array The ConfigMagic configuration.
     */
    protected $config = array();

    /**
     * @var array A list of all of the configs specified in the template file.
     */
    protected $configs = array();

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
; ##PROFILE##        => The current "profile" name (ie dev/staging/production)
; ##CONFIG##        => The current "config" name (ie httpd.conf, sh.conf)
; For each config that ConfigMagic will handle, you need 2 entires under "templates":
;   - <config>.configFileTemplate => path to the input template file
;   - <config>.configFile         => path to the write output config file to
[templates]
example.configFileTemplate = ##CONFIG_DIR##/##CONFIG##.conf
example.configFile         = ##CONFIG_DIR##/##CONFIG##-##PROFILE##.conf

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

    protected function getConfigMagicConfigPath()
    {
        return $this->getConfigDirectory() . '/config.ini';
    }

    protected function readConfig()
    {
        $iniFile = $this->getConfigMagicConfigPath();
        $iniFileData = parse_ini_file($iniFile, true);

        // determine profiles
        if (!isset($iniFileData['templates'])) throw new Exception("No 'templates' section in ConfigMagic config!");

        $this->configs = array();
        foreach (array_keys($iniFileData['templates']) as $templateKey)
        {
            $matches = array();
            if (preg_match('/^([^\.]+)\.configFileTemplate$/', $templateKey, $matches))
            {
                $config = $matches[1];
                $this->configs[$config]['configFileTemplate'] = $iniFileData['templates']["{$config}.configFileTemplate"];
                $this->configs[$config]['configFile'] = $iniFileData['templates']["{$config}.configFile"];
            }
        }
    }

    public function writeConfigForProfile($profile)
    {
        $profileFile = $this->getConfigDirectory() . '/' . $profile . '.ini';
        if (!file_exists($profileFile)) throw new Exception("Could not load profile {$profile} from {$profileFile}.");

        foreach (array_keys($this->configs) as $config) {
            $this->logMessage("\n{$config}\n");

            $configFileTemplate = $this->replaceTokens($this->configs[$config]['configFileTemplate'], $profile, $config);
            $configFile = $this->replaceTokens($this->configs[$config]['configFile'], $profile, $config);

            $this->logMessage("{$config}: Creating {$configFile} from template {$configFileTemplate}.\n");

            // coalesce data
            $defaultData = parse_ini_file($this->getConfigMagicConfigPath());
            $profileData = parse_ini_file($profileFile);
            $coalescePath = array(
                $defaultData,
                $profileData
            );
            $coalescedData = array_merge($defaultData, $profileData);

            // load template
            if (!file_exists($configFileTemplate)) throw new Exception("{$config}: ConfigFileTemplate {$configFileTemplate} does not exist.");
            $configFileTemplateString = file_get_contents($configFileTemplate);
            if (!$configFileTemplateString) throw new Exception("{$config}: Unknown error reading configFileTemplate {$configFileTemplate}.");

            // replace tokens in template
            $replacements = array();
            foreach ($coalescedData as $k => $v) {
                // for each token, process with all replacements up-to-now as well
                $replacements["##{$k}##"] = str_replace(array_keys($replacements), array_values($replacements), $v);
            }
            $configFileTemplateString = str_replace(array_keys($replacements), array_values($replacements), $configFileTemplateString);
            // issue warnings for warnings for missing ##var.name##
            $matches = array();
            if (preg_match_all('/(##[A-z0-9-_\.]+##)/', $configFileTemplateString, $matches)) {
                $uniqueMisses = array();
                foreach ($matches[0] as $missed) {
                    $uniqueMisses[$missed] = 1;
                }
                foreach (array_keys($uniqueMisses) as $missed) {
                    $this->logMessage("{$config}: No subtitution found for: {$missed}\n");
                }
            }
            // write out
            $ok = file_put_contents($configFile, $configFileTemplateString);
            if (!$ok) throw new Exception("{$config}: Error writing out config file {$configFile}.");
        }
    }

    protected function replaceTokens($input, $profile, $config)
    {
        $input = str_replace(array(
                                '##CONFIG_DIR##',
                                '##PROFILE##',
                                '##CONFIG##',
                             ),
                             array(
                                $this->getConfigDirectory(),
                                $profile,
                                $config,
                             ),
                             $input);
        return $input;
    }
}

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
    const OPT_OUTPUT_DIR                 = 'outputDir';
    const OPT_VERBOSE                    = 'verbose';
    const OPT_QUIET                      = 'quiet';

    /**
     * @var string The path to the directory where config.ini, templates, and profiles are stored.
     */
    protected $configDir;
    /**
     * @var string The path to the directory where config files are output to. Defaults to {@link ConfigMagic::$configDir configDir}.
     */
    protected $outputDir;

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
                                ConfigMagic::OPT_OUTPUT_DIR            => NULL,
                                ConfigMagic::OPT_VERBOSE               => false,
                                ConfigMagic::OPT_QUIET                 => false,
                           ), $opts);

        $this->logMessage("ConfigMagic - The PHP Configuration Organizer.\n");
        $this->initializeConfigDir($opts[ConfigMagic::OPT_CONFIG_DIR]);

        // set up initial data
        $this->setConfigDirectory($opts[ConfigMagic::OPT_CONFIG_DIR]);
        $this->setOutputDirectory($opts[ConfigMagic::OPT_OUTPUT_DIR]);
        $this->verbose = $opts[ConfigMagic::OPT_VERBOSE];
        $this->quiet = $opts[ConfigMagic::OPT_QUIET];

        $this->readConfig();
    }

    protected function initializeConfigDir($configDir)
    {
        // initialize directory
        if (!file_exists($configDir))
        {
            $this->logMessage("Config directory does not exist.\nInitializing new config directory at {$configDir}:\n");

            foreach (array($configDir, $configDir . '/templates', $configDir . '/profiles') as $d) {
                $this->logMessage("mkdir {$d}\n");
                if (!file_exists($d))
                {
                    $ok = mkdir($d, 0777, true);
                    if (!$ok) throw new Exception("Failed to create config directory: {$d}");
                }
            }
            $cleanTPL = <<<END
; The "templates" directive is a special directive that lists all config templates managed by ConfigMagic
; There are a handful of tokens that you can use in your values to use dynamic data:
; ##CONFIG_DIR##    => Absolute path to the config directory. You can then use relative paths to precisely control input/output location for your config files.
; ##TEMPLATES_DIR## => Absolute path to the templates directory. You can then use relative paths to precisely control input/output location for your config files.
; ##OUTPUT_DIR##    => Absolute path to the output directory. You can then use relative paths to precisely control input/output location for your config files.
; ##PROFILE##       => The current "profile" name (ie dev/staging/production)
; ##CONFIG##        => The current "config" name (ie httpd.conf, sh.conf)
; For each config that ConfigMagic will handle, you need 2 entires under "templates":
;   - <config>.configFileTemplate => path to the input template file
;   - <config>.configFile         => path to the write output config file to
[templates]
example.configFileTemplate = ##TEMPLATES_DIR##/##CONFIG##.conf
example.configFile         = ##OUTPUT_DIR##/##CONFIG##.conf

[data]
; your default data here. any settings here will be overridden by values in the profile's ini file on a setting-by-setting basis
END;
            // ' fix crap syntax coloring
            file_put_contents($configDir . '/config.ini', $cleanTPL);
        }
    }

    public function setConfigDirectory($d)
    {
        $realpath = realpath($d);
        if ($realpath === false) throw new Exception("realpath({$d}) failed.");
        $this->configDir = $realpath;
        return $this;
    }

    public function getConfigDirectory()
    {
        return $this->configDir;
    }

    public function setOutputDirectory($d)
    {
        if ($d !== NULL)
        {
            $realpath = realpath($d);
            if ($realpath === false) throw new Exception("realpath({$d}) failed.");

            $d = $realpath;
        }
        $this->outputDir = $d;
        return $this;
    }

    public function getOutputDirectory()
    {
        if ($this->outputDir !== NULL)
        {
            return $this->outputDir;
        }
        return $this->getConfigDirectory();
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

    /**
     * Write out all config files for the given profile.
     *
     * @param string Profile name.
     * @throws object Exception
     */
    public function writeConfigForProfile($profile)
    {
        $profileFile = $this->getConfigDirectory() . '/profiles/' . $profile . '.ini';
        if (!file_exists($profileFile)) throw new Exception("Could not load profile {$profile} from {$profileFile}.");

        // make sure output dir exists
        $outputDir = $this->getOutputDirectory();
        if (!file_exists($outputDir))
        {
            mkdir($this->getOutputDirectory());
            $this->logMessage("Output directory does not exist.\nCreating output directory at {$outputDir}.\n");
        }

        $substitutionErrors = false;
        foreach (array_keys($this->configs) as $config) {
            $this->logMessage("\n{$config}\n");

            $configFileTemplate = $this->replaceTokens($this->configs[$config]['configFileTemplate'], $profile, $config);
            $configFile = $this->replaceTokens($this->configs[$config]['configFile'], $profile, $config);
            if ($configFile == $configFileTemplate) throw new Exception("{$config}: configFile and configFileTemplate cannot be the same. Both are set to: {$configFile}.");

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

            // replace tokens in template
            $replacements = $this->getReplacementTokens($profile, $config);
            foreach ($coalescedData as $k => $v) {
                // for each token, process with all replacements up-to-now as well
                $replacements["##{$k}##"] = str_replace(array_keys($replacements), array_values($replacements), $v);
            }
            // process php tag magic
            foreach ($replacements as $k => $v) {
                if (preg_match('/<\?php (.*)\?'.'>/', $v, $matches))    // goofy syntax there to prevent syntax coloring problems in rest of file due to close    php tag
                {
                    $replacements[$k] = eval( "return {$matches[1]};" );
                }
            }

            // process template as PHP
            ob_start();
            $profileData = $replacements;
            include $configFileTemplate;
            $configFileTemplateString = ob_get_contents();
            ob_end_clean();
            $configFileTemplateString = str_replace(array_keys($replacements), array_values($replacements), $configFileTemplateString);
            // issue warnings for warnings for missing ##var.name##
            $matches = array();
            if (preg_match_all('/(##[A-z0-9-_\.]+##)/', $configFileTemplateString, $matches)) {
                $uniqueMisses = array();
                foreach ($matches[0] as $missed) {
                    $uniqueMisses[$missed] = 1;
                }
                foreach (array_keys($uniqueMisses) as $missed) {
                    $substitutionErrors = true;
                    $this->logMessage("{$config}: No subtitution found for: {$missed}\n");
                }
            }
            // write out
            if (file_exists($configFile))
            {
                unlink($configFile);
            }
            $ok = file_put_contents($configFile, $configFileTemplateString);
            if ($ok === false) throw new Exception("{$config}: Error writing out config file {$configFile}.");
            // make read-only to minimize risk of editing the generated conf vs the template
            chmod($configFile, 0444);
        }
        if ($substitutionErrors)
        {
            throw new Exception("Some variables could not be substitued. This could cause dangerous side-effects in your config files.");
        }
    }

    protected function getReplacementTokens($profile, $config)
    {
        return array(
                '##CONFIG_DIR##' => $this->getConfigDirectory(),
                '##OUTPUT_DIR##' => $this->getOutputDirectory(),
                '##TEMPLATES_DIR##' => $this->getConfigDirectory() . '/templates',
                '##PROFILE##' => $profile,
                '##CONFIG##' => $config,
                );
    }
    protected function replaceTokens($input, $profile, $config)
    {
        $replacements = $this->getReplacementTokens($profile, $config);
        return str_replace(array_keys($replacements), array_values($replacements), $input);
    }
}

<?php

class Config
{
    const CONFIG_VERSION = "2.2.1.6";

    protected static $config     = null;
    protected static $configHash = null;

    protected static $__isInitialized = false;
    protected static function __initialize()
    {
        if (self::$__isInitialized == true) return;
        self::$__isInitialized = true;

        self::$config = Arr();

        self::$configHash = self::CONFIG_VERSION . "-" . ((@file_exists(APPLICATION_FOLDER . "Config/") && @is_dir(APPLICATION_FOLDER . "Config/") && file_exists(APPLICATION_FOLDER . "Config/Application.json"))
            ? @filemtime(APPLICATION_FOLDER . "Config/Application.json") : 0) . "-";
//
        if (@is_dir(APPLICATION_FOLDER . "Config/Application/")) {
            $scanSid = @scandir(APPLICATION_FOLDER . "Config/Application/");
            if ($scanSid != null && count($scanSid) > 0)
                foreach ($scanSid as $_scanSid)
                    if ($_scanSid != "." && $_scanSid != ".." && stringEx($_scanSid)->endsWith(".json"))
                        self::$configHash .= @filemtime(APPLICATION_FOLDER . "Config/Application/" . $_scanSid) . "-";
        }

        self::$configHash = sha1(self::$configHash);
        if (\File::exists(\Garbage\Cache::getCachePath() . ".krupabox/config/" . self::$configHash . ".blob"))
        { self::loadFromCache(); return null; }


        // FALLBACK: Parse old xmls to json
        if (\File::exists(APPLICATION_FOLDER . "Config/Application.xml") && \File::exists(APPLICATION_FOLDER . "Config/Application.json") == false) {
            $xmlData = \File::getContents(APPLICATION_FOLDER . "Config/Application.xml");
            $xmlData = \Serialize\Xml::decode($xmlData);
            $newData = Arr();
            if ($xmlData->containsKey(Config)) {
                $xmlData->Config = Arr($xmlData->Config);
                foreach ($xmlData->Config as $key => $value) {
                    $splitKey = stringEx($key)->split(".");
                    if ($splitKey->count == 1)
                        $newData[$key] = stringEx($value)->trim("\r\n\t");
                    elseif ($splitKey->count >= 2)
                    {
                        if ($newData->containsKey($splitKey[0]) == false)
                            $newData[($splitKey[0])] = Arr();
                        if ($splitKey[1] == "uniqueSidPaths")  {
                            $value = (($value->containsKey(uniqueSidPath) ? $value->uniqueSidPath : null));
                            $newArr = Arr();
                            foreach ($value as $_value)
                                $newArr->add(stringEx($_value)->trim("\r\n\t"));
                            $value = $newArr;
                        } else $value = stringEx($value)->trim("\r\n\t");
                        $newData[($splitKey[0])][($splitKey[1])] = $value;
                    }
                }
            }
            \File::setContents(APPLICATION_FOLDER . "Config/Application.json", $newData);
        }
        // End parse old xmls to json

        $applicationConfigPath = \File::getRealPath(APPLICATION_FOLDER . "Config/Application.json");
        $applicationConfigData = \File::getContents($applicationConfigPath);

        self::$config = \Serialize\Json::decode($applicationConfigData);
        if (self::$config == null) self::$config = Arr();

        if (!self::$config->containsKey(server))
            self::$config->server = Arr();

        if (!self::$config->server->containsKey(sid))
            self::$config->server->sid = null;

        if (self::$config->server->containsKey(uniqueSidPaths))
        {
            foreach (self::$config->server->uniqueSidPaths as $serverSidPath)
            {
                $serverSidPath = \File::getInsensitivePath($serverSidPath);
                if (\File::exists($serverSidPath))
                {
                    $serverSidPathData = \File::getContents($serverSidPath);
                    $serverSidPathData = stringEx($serverSidPathData)->trim("\r\n\t");

                    if (!stringEx($serverSidPathData)->isEmpty())
                    { self::$config->server->sid = $serverSidPathData; break; }
                }
            }

            self::$config->server->removeKey(uniqueSidPaths);
        }

        if (self::$config->server->sid != null)
        {
            $applicationExtraConfigPath = \File::getRealPath(APPLICATION_FOLDER . "Config/Application/" . self::$config->server->sid . ".json");

            // FALLBACK: Parse old xmls to json
            if (\File::exists(APPLICATION_FOLDER . "Config/Application/" . self::$config->server->sid . ".xml") && \File::exists(APPLICATION_FOLDER . "Config/Application/" . self::$config->server->sid . ".json") == false) {
                $xmlData = \File::getContents(APPLICATION_FOLDER . "Config/Application/" . self::$config->server->sid . ".xml");
                $xmlData = \Serialize\Xml::decode($xmlData);
                $newData = Arr();
                if ($xmlData->containsKey(Config)) {
                    $xmlData->Config = Arr($xmlData->Config);
                    foreach ($xmlData->Config as $key => $value) {
                        $splitKey = stringEx($key)->split(".");
                        if ($splitKey->count == 1)
                            $newData[$key] = stringEx($value)->trim("\r\n\t");
                        elseif ($splitKey->count >= 2)
                        {
                            if ($newData->containsKey($splitKey[0]) == false)
                                $newData[($splitKey[0])] = Arr();
                            if ($splitKey[1] == "uniqueSidPaths")  {
                                $value = (($value->containsKey(uniqueSidPath) ? $value->uniqueSidPath : null));
                                $newArr = Arr();
                                foreach ($value as $_value)
                                    $newArr->add(stringEx($_value)->trim("\r\n\t"));
                                $value = $newArr;
                            } else $value = stringEx($value)->trim("\r\n\t");
                            $newData[($splitKey[0])][($splitKey[1])] = $value;
                        }
                    }
                }

                \File::setContents(APPLICATION_FOLDER . "Config/Application/" . self::$config->server->sid . ".json", $newData);
            }
            // End parse old xmls to json

            $applicationExtraConfigData = \File::getContents($applicationExtraConfigPath);
            $extraConfig = \Serialize\Json::decode($applicationExtraConfigData);
            self::$config = self::$config->merge($extraConfig);
        }

        self::checkMissingVars();
        self::onLoadConfig();
        self::saveToCache();
    }

    protected static function checkMissingVars()
    {
        // Server
        if (!self::$config->containsKey(server))
            self::$config->server = Arr();

        if (!self::$config->containsKey(app))
            self::$config->app = Arr();

        if (!self::$config->app->containsKey(name))
            self::$config->app->name = "";
        if (!self::$config->app->containsKey(version))
            self::$config->app->version = "";
        if (!self::$config->app->containsKey(developed))
            self::$config->app->developed = "";

        if (!self::$config->app->containsKey(language))
            self::$config->app->language = "en";

        self::$config->app->versionId = stringEx(self::$config->app->version)->getOnlyNumbers(".");
        while (stringEx(self::$config->app->versionId)->contains(".."))
            self::$config->app->versionId = stringEx(self::$config->app->versionId)->replace("..", ".");
        if (stringEx(self::$config->app->versionId)->isEmpty())
            self::$config->app->versionId = "1.0";

        if (!self::$config->containsKey(connection))
            self::$config->connection = Arr();
        if (!self::$config->connection->containsKey(keepAlive))
            self::$config->connection->keepAlive = false;
        if (!self::$config->connection->containsKey(onlySecure))
            self::$config->connection->onlySecure = false;
        if (!self::$config->connection->containsKey(onlyWWW))
            self::$config->connection->onlyWWW = false;
        if (!self::$config->connection->containsKey(onlyNonWWW))
            self::$config->connection->onlyNonWWW = false;

        if (self::$config->connection->onlyWWW == true && self::$config->connection->onlyNonWWW == true)
            self::$config->connection->onlyNonWWW = false;
        
        if (!self::$config->server->containsKey(domain))
            self::$config->server->domain = "localhost";
        self::$config->server->domain = stringEx(self::$config->server->domain)->isEmpty() ? localhost : self::$config->server->domain;

        if (!self::$config->server->containsKey(timezone))
            self::$config->server->timezone = "UTC";

        self::$config->server->timezone = stringEx(self::$config->server->timezone)->trim("\r\n\t");

        if (!self::$config->server->containsKey(environment))
            self::$config->server->environment = development;
        self::$config->server->environment = stringEx(self::$config->server->environment)->toLower();
        if (self::$config->server->environment != release && self::$config->server->environment != development)
            self::$config->server->environment = development;

        if (!self::$config->server->containsKey(maintence))
            self::$config->server->maintence = false;
        self::$config->server->maintence = boolEx(self::$config->server->maintence)->toBool();

        if (!self::$config->server->containsKey(maintenceIps))
            self::$config->server->maintenceIps = Arr();
        self::$config->server->maintenceIps = Arr(self::$config->server->maintenceIps);

        if (!self::$config->server->containsKey(sid))
            self::$config->server->sid = null;

        if (!self::$config->server->containsKey(cryptographyKey))
            self::$config->server->cryptographyKey = "KRUPABOX_CRYPTOGRAPHY_KEY_DEFAULT";

        // Database
        if (!self::$config->containsKey(database))
            self::$config->database = Arr();

        if (!self::$config->database->containsKey(sid))
            self::$config->database->sid = null;

        if (!self::$config->database->containsKey(driver))
            self::$config->database->driver = null;
        self::$config->database->driver = stringEx(self::$config->database->driver)->toLower();
        self::$config->database->driver = stringEx(self::$config->database->driver)->isEmpty() ? null : self::$config->database->driver;

        if (!self::$config->database->containsKey(username))
            self::$config->database->username = root;
        self::$config->database->username = stringEx(self::$config->database->username)->isEmpty() ? root : self::$config->database->username;

        if (!self::$config->database->containsKey(host))
            self::$config->database->host = localhost;
        self::$config->database->host = stringEx(self::$config->database->host)->isEmpty() ? localhost : self::$config->database->host;

        if (!self::$config->database->containsKey(password))
            self::$config->database->password = null;
        self::$config->database->password = stringEx(self::$config->database->password)->isEmpty() ? null : self::$config->database->password;

        if (!self::$config->database->containsKey(cache))
            self::$config->database->cache = true;
        self::$config->database->cache = boolEx(self::$config->database->cache)->toBool();

        if (!self::$config->database->containsKey(cacheType))
            self::$config->database->cacheType = filesystem;
        self::$config->database->cacheType = stringEx(self::$config->database->cacheType)->toLower();
        if (self::$config->database->cacheType != filesystem && self::$config->database->cacheType != memcache && self::$config->database->cacheType != memcached)
            self::$config->database->cacheType = filesystem;

        if (!self::$config->database->containsKey(cacheHost))
            self::$config->database->cacheHost = localhost;
        self::$config->database->cacheHost = stringEx(self::$config->database->cacheHost)->isEmpty() ? localhost : self::$config->database->cacheHost;

        if (!self::$config->database->containsKey(cachePort))
            self::$config->database->cachePort = 11211;
        self::$config->database->cachePort = stringEx( self::$config->database->cachePort)->getOnlyNumbers();
        self::$config->database->cachePort = stringEx(self::$config->database->cachePort)->isEmpty() ? 11211 : self::$config->database->cachePort;

        if (!self::$config->database->containsKey(migration))
            self::$config->database->migration = true;
        self::$config->database->migration = \boolEx(self::$config->database->migration);

        // Render
        if (!self::$config->containsKey(render))
            self::$config->render = Arr();

        if (!self::$config->render->containsKey(cache))
            self::$config->render->cache = true;
        self::$config->render->cache = boolEx(self::$config->render->cache)->toBool();

        if (!self::$config->render->containsKey(cacheType))
            self::$config->render->cacheType = filesystem;
        self::$config->render->cacheType = stringEx(self::$config->render->cacheType)->toLower();
        if (self::$config->render->cacheType != filesystem && self::$config->render->cacheType != memcache && self::$config->render->cacheType != memcached)
            self::$config->render->cacheType = filesystem;

        if (!self::$config->render->containsKey(cacheHost))
            self::$config->render->cacheHost = localhost;
        self::$config->render->cacheHost = stringEx(self::$config->render->cacheHost)->isEmpty() ? localhost : self::$config->render->cacheHost;

        if (!self::$config->render->containsKey(cachePort))
            self::$config->render->cachePort = 11211;
        self::$config->render->cachePort = stringEx( self::$config->render->cachePort)->getOnlyNumbers();
        self::$config->render->cachePort = stringEx(self::$config->render->cachePort)->isEmpty() ? 11211 : self::$config->render->cachePort;

        // CommandLine
        if (!self::$config->containsKey(commandline))
            self::$config->commandline = Arr();
        if (!self::$config->commandline->containsKey(path))
            self::$config->commandline->path = null;

        self::$config->commandLine = self::$config->commandline;
        self::$config->removeKey(commandline);

        if (!self::$config->containsKey(compiler))
            self::$config->compiler = Arr();

        if (self::$config->compiler->containsKey(model))
            self::$config->compiler->model = boolEx(self::$config->compiler->model)->toBool();
        else self::$config->compiler->model = true;

        if (self::$config->compiler->containsKey(controller))
            self::$config->compiler->controller = boolEx(self::$config->compiler->controller)->toBool();
        else self::$config->compiler->controller = true;

        if (self::$config->compiler->containsKey(component))
            self::$config->compiler->component = boolEx(self::$config->compiler->component)->toBool();
        else self::$config->compiler->component = true;

        // Python
        if (!self::$config->containsKey(python))
            self::$config->python = Arr();
        if (!self::$config->python->containsKey(path))
            self::$config->python->path = null;

        // PHARs
        if (!self::$config->containsKey(phar))
            self::$config->phar = Arr();
        if (!self::$config->phar->containsKey(enabled))
            self::$config->phar->enabled = true;
        else self::$config->phar->enabled = boolEx(self::$config->phar->enabled)->toBool();

        if (!self::$config->containsKey(cache))
            self::$config->cache = Arr();

        if (!self::$config->containsKey(admin))
            self::$config->admin = Arr();

        // subdomain parse
        if (!self::$config->admin->containsKey(mysqlSubdomain))
            self::$config->admin->mysqlSubdomain = "mysql";
        self::$config->admin->mysqlSubdomain = stringEx(self::$config->admin->mysqlSubdomain)->toLower();
        self::$config->admin->mysqlSubdomain = stringEx(self::$config->admin->mysqlSubdomain)->removeAccents();

        while (stringEx(self::$config->admin->mysqlSubdomain)->contains(".."))
            self::$config->admin->mysqlSubdomain = stringEx(self::$config->admin->mysqlSubdomain)->replace("..", ".");
        while (stringEx(self::$config->admin->mysqlSubdomain)->startsWith("."))
            self::$config->admin->mysqlSubdomain = stringEx(self::$config->admin->mysqlSubdomain)->subString(1);
        while (stringEx(self::$config->admin->mysqlSubdomain)->endsWith("."))
            self::$config->admin->mysqlSubdomain = stringEx(self::$config->admin->mysqlSubdomain)->subString(0, stringEx(self::$config->admin->mysqlSubdomain)->length - 1);

        if (!self::$config->containsKey(front))
            self::$config->front = Arr();
        if (!self::$config->front->containsKey(base))
            self::$config->front->base = "";

        if (stringEx(self::$config->front->base)->isEmpty() == false)
        {
            self::$config->front->base = stringEx(self::$config->front->base)->replace("\\", "/");
            while (stringEx(self::$config->front->base)->contains("//"))
                self::$config->front->base = stringEx(self::$config->front->base)->replace("//", "/");
            while (stringEx(self::$config->front->base)->startsWith("/"))
                self::$config->front->base = stringEx(self::$config->front->base)->subString(1);
            while (stringEx(self::$config->front->base)->endsWith("/"))
                self::$config->front->base = stringEx(self::$config->front->base)->subString(0, (stringEx(self::$config->front->base)->length - 1));

            if (stringEx(self::$config->front->base)->isEmpty() == false)
                self::$config->front->base = ("/" . self::$config->front->base);
        }

        if (!self::$config->front->containsKey(polyfill))
            self::$config->front->polyfill = Arr();

        self::$config->front->polyfill = Arr(self::$config->front->polyfill);

        if (!self::$config->front->polyfill->containsKey(blob))
            self::$config->front->polyfill->blob = true;
        if (!self::$config->front->polyfill->containsKey(canvas))
            self::$config->front->polyfill->canvas = true;
        if (!self::$config->front->polyfill->containsKey(legacyBrowser))
            self::$config->front->polyfill->legacyBrowser = true;
        if (!self::$config->front->polyfill->containsKey(ecmaScript5))
            self::$config->front->polyfill->ecmaScript5 = true;
        if (!self::$config->front->polyfill->containsKey(ecmaScript6))
            self::$config->front->polyfill->ecmaScript6 = true;
        if (!self::$config->front->polyfill->containsKey(ecmaScript7))
            self::$config->front->polyfill->ecmaScript7 = true;
        if (!self::$config->front->polyfill->containsKey(html5))
            self::$config->front->polyfill->html5 = true;
        if (!self::$config->front->polyfill->containsKey(keyboard))
            self::$config->front->polyfill->keyboard = true;
        if (!self::$config->front->polyfill->containsKey(typedArray))
            self::$config->front->polyfill->typedArray = true;
        if (!self::$config->front->polyfill->containsKey(url))
            self::$config->front->polyfill->url = true;
        if (!self::$config->front->polyfill->containsKey(web))
            self::$config->front->polyfill->web = true;
        if (!self::$config->front->polyfill->containsKey(webAudio))
            self::$config->front->polyfill->webAudio = true;
        if (!self::$config->front->polyfill->containsKey(xhr))
            self::$config->front->polyfill->xhr = true;
        if (!self::$config->front->polyfill->containsKey(promise))
            self::$config->front->polyfill->promise = true;
        if (!self::$config->front->polyfill->containsKey(prefix))
            self::$config->front->polyfill->prefix = false;

        self::$config->front->polyfill->blob = \toBool(self::$config->front->polyfill->blob);
        self::$config->front->polyfill->canvas = \toBool(self::$config->front->polyfill->canvas);
        self::$config->front->polyfill->ecmaScript5 = \toBool(self::$config->front->polyfill->ecmaScript5);
        self::$config->front->polyfill->ecmaScript6 = \toBool(self::$config->front->polyfill->ecmaScript6);
        self::$config->front->polyfill->ecmaScript7 = \toBool(self::$config->front->polyfill->ecmaScript7);
        self::$config->front->polyfill->html5 = \toBool(self::$config->front->polyfill->html5);
        self::$config->front->polyfill->keyboard = \toBool(self::$config->front->polyfill->keyboard);
        self::$config->front->polyfill->typedArray = \toBool(self::$config->front->polyfill->typedArray);
        self::$config->front->polyfill->url = \toBool(self::$config->front->polyfill->url);
        self::$config->front->polyfill->web = \toBool(self::$config->front->polyfill->web);
        self::$config->front->polyfill->webAudio = \toBool(self::$config->front->polyfill->webAudio);
        self::$config->front->polyfill->xhr = \toBool(self::$config->front->polyfill->xhr);
        self::$config->front->polyfill->promise = \toBool(self::$config->front->polyfill->promise);


        if (!self::$config->front->containsKey(support))
            self::$config->front->support = Arr();

        if (!self::$config->front->support->containsKey(render))
            self::$config->front->support->render = true;
        if (!self::$config->front->support->containsKey(validate))
            self::$config->front->support->validate = true;
        if (!self::$config->front->support->containsKey(webgl))
            self::$config->front->support->webgl = false;
        if (!self::$config->front->support->containsKey(screenshot))
            self::$config->front->support->screenshot = false;
        if (!self::$config->front->support->containsKey(gyroscope))
            self::$config->front->support->gyroscope = false;
        if (!self::$config->front->support->containsKey(prefix))
            self::$config->front->support->prefix = false;

        self::$config->front->support->render = \toBool(self::$config->front->support->render);
        self::$config->front->support->validate = \toBool(self::$config->front->support->validate);
        self::$config->front->support->webgl = \toBool(self::$config->front->support->webgl);
        self::$config->front->support->screenshot = \toBool(self::$config->front->support->screenshot);
        self::$config->front->support->gyroscope = \toBool(self::$config->front->support->gyroscope);
        self::$config->front->support->prefix = \toBool(self::$config->front->support->prefix);

        if (!self::$config->front->containsKey(assets))
            self::$config->front->assets = Arr();

        if (!self::$config->front->assets->containsKey(js))
            self::$config->front->assets->js = Arr();
        if (!self::$config->front->assets->js->containsKey(minify))
            self::$config->front->assets->js->minify = true;

        if (!self::$config->front->assets->containsKey(css))
            self::$config->front->assets->css = Arr();
        if (!self::$config->front->assets->css->containsKey(minify))
            self::$config->front->assets->css->minify = true;
        if (!self::$config->front->assets->css->containsKey(autoPrefix))
            self::$config->front->assets->css->autoPrefix = false;

        if (!self::$config->containsKey(output))
            self::$config->output = Arr();

        if (!self::$config->output->containsKey(gzip))
            self::$config->output->gzip = "true";
        if (!self::$config->output->containsKey(deflate))
            self::$config->output->deflate = "true";

        self::$config->output->gzip = \boolEx(self::$config->output->gzip)->toBool();
        self::$config->output->deflate = \boolEx(self::$config->output->deflate)->toBool();
    }

    protected static function stringKeysToArrayData($data)
    {
        $withDefaultKey = Arr();
        $withDeepKeys   = Arr();

        foreach ($data as $key => $value)
        {
            if (!stringEx($key)->contains("."))
            { $withDefaultKey[$key] = $value; continue;  }

            $split = stringEx($key)->split(".");
            $deepKey = "";

            foreach ($split as $_split)
                if ($_split != $split[0])
                    $deepKey .= $_split . ".";

            if (stringEx($deepKey)->endsWith("."))
                $deepKey = stringEx($deepKey)->subString(0, -1);

            if (!$withDeepKeys->containsKey($split[0]))
                $withDeepKeys[($split[0])] = Arr();

            $withDeepKeys[($split[0])][$deepKey] = $value;
            $deepArray = self::stringKeysToArrayData($withDeepKeys[($split[0])]);
            $withDeepKeys[($split[0])] = $deepArray;
        }

        $data = Arr();
        foreach ($withDefaultKey as $key => $value) $data[$key] = $value;
        foreach ($withDeepKeys as $key => $value)   $data[$key] = $value;

        return $data;
    }

    public static function get()
    {
        self::__initialize();
        return self::$config;
    }

    protected static function loadFromCache()
    {
        \Import::PHP(\Garbage\Cache::getCachePath() . ".krupabox/config/" . self::$configHash . ".blob");
    }

    public static function __fromCache($cachedConfig)
    {
        self::$config = Arr($cachedConfig);
    }

    protected static function saveToCache()
    {
        $scanSid = @scandir(\Garbage\Cache::getCachePath() . ".krupabox/config/");
        if ($scanSid != null && count($scanSid) > 0)
            foreach ($scanSid as $_scanSid)
                if ($_scanSid != "." && $_scanSid != ".." && stringEx($_scanSid)->endsWith(".blob"))
                    \File::delete(\Garbage\Cache::getCachePath() . ".krupabox/config/" . $_scanSid);

        ob_start(); \var_export(self::$config->toArray()); $buffer = ob_get_contents(); ob_end_clean();
        $varExport = ('<?php \Config::__fromCache(' . $buffer . ');');
        \File::setContents(\Garbage\Cache::getCachePath() . ".krupabox/config/" . self::$configHash . ".blob", $varExport);
    }

    protected static function onLoadConfig()
    {
        \Language::setDefaultISO(self::$config->app->language);
        self::$config->app->language = \Language::getDefaultISO();
    }
}
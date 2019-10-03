<?php

namespace
{
    // Deprecated
    const IS_DEVELOPMENT = true;
    const VISUAL_DEBUGGER_ENABLED = true;
    const CONSOLE_DEBUGGER_ENABLED = true;
    const AJAX_DEBUGGER_ENABLED = true;
    const FILE_DEBUGGER_ENABLED = true;

    function scalar($value)
    {
        $type = gettype($value);

        if ($type == "string")
            return \stringEx($value);
        elseif ($type == "integer")
            return \intEx($value);
        elseif ($type == "double")
            return \floatEx($value);
        elseif ($type == "boolean")
            return \boolEx($value);

        return $value;
    }
}

namespace KrupaBOX\Internal
{
    define("APPLICATION_FOLDER", (__KRUPA_PATH_ROOT__ . "Application/"));
    define("SERVER_FOLDER", (APPLICATION_FOLDER . "Server/"));
    define("CLIENT_FOLDER", (APPLICATION_FOLDER . "Client/"));

    class Kernel
    {
        protected static $isDonePredefinedCheck = false;
        
        protected static $predefinedClasses = null;
        protected static $predefinedFunctions = null;
        
        protected static $instance = null;
        protected static $isCoreLoaded = false;
        protected static $isOutsideCoreLoad = false;

        protected static $filePathsToRegisterIncludeArr = [];
        
        public static function isCoreLoaded()
        { return self::$isCoreLoaded; }

        public static function filePathsToRegisterInclude($filePath, $include = true)
        {
            $filePathsToRegisterIncludeArr[] = $filePath;

            if ($include == true) {
                if (method_exists('Import','PHP'))
                    \Import::PHP($filePath);
                else \KrupaBOX\Internal\Engine::includeInsensitive($filePath);
            }
        }

        public static function onInitialize()
        {
            //self::hookPredefined();
            self::$instance = new self();

            //@\apache_setenv('no-gzip', 1);
            @\ini_set('zlib.output_compression', 0);
            @\ini_set('implicit_flush', 1);

            \KrupaBOX\Internal\Error::register("default");
            \KrupaBOX\Internal\Core::load();

            if (function_exists("mb_strtolower") == false || mb_strtolower("Index") != "index")
            { echo json_encode(["error" => "INTERNAL_SERVER_ERROR", "message" => "Missing MBSTRING extension."]); exit; }

            self::$isCoreLoaded = true;

            \KrupaBOX\Internal\Library::load("Symfony");

            // Events: onAwake
            if (\File::exists(SERVER_FOLDER . "Event/OnAwake.php")) {
                \Import::PHP(SERVER_FOLDER . "Event/OnAwake.php");
                if (\ClassEx::exists("Application\\Server\\Event\\OnAwake")) {
                    $instanceName = ("Application\\Server\\Event\\OnAwake");
                    $reflector = new \ReflectionClass($instanceName);
                    if ($reflector->hasMethod("onAwake")) {
                        $method = $reflector->getMethod("onAwake");
                        if ($method->isStatic() && $method->isPublic())
                            $instanceName::onAwake();
                    }
                }
            }

            \KrupaBOX\Internal\Routes::run();
        }

        public function __construct()
        { spl_autoload_register([__CLASS__, 'autoLoader']); }
        
        public function autoLoader($className)
        {
            if (self::$isCoreLoaded == false)
            {
                $filePath = (__KRUPA_PATH_INTERNAL__ . $className);
                $filePath = str_replace("KrupaBOX/KrupaBOX", "KrupaBOX", $filePath);
                $filePath = str_replace("\\", "/", $filePath) . ".php";
                
                if (@file_exists($filePath))
                    \KrupaBOX\Internal\Engine::includeInsensitive($filePath);
    
                return null;
            }
            
            \KrupaBOX\Internal\Loader::handler($className);
        }
        
        protected static function hookPredefined()
        {
            if (self::$isDonePredefinedCheck == true)
                return;
                
            self::$predefinedClasses = get_declared_classes();
            self::$predefinedFunctions = get_defined_functions();

            unset(self::$predefinedClasses[(count(self::$predefinedClasses) - 1)]);
            unset(self::$predefinedClasses[(count(self::$predefinedClasses) - 1)]);

            if (!isset(self::$predefinedFunctions["internal"]))
                self::$predefinedFunctions = [];
            else self::$predefinedFunctions = self::$predefinedFunctions["internal"];
            
            self::$isDonePredefinedCheck = true;
        }
        
        public static function getPredefinedClasses()
        {
            if (self::$isDonePredefinedCheck == true)
                return self::$predefinedClasses;

            self::hookPredefined();
            return self::$predefinedClasses;
        }
        
        public static function getPredefinedFunctions()
        {
            if (self::$isDonePredefinedCheck == true)
                return self::$predefinedFunctions;

            self::hookPredefined();
            return self::$predefinedFunctions;
        }
    }

    Kernel::onInitialize();
}
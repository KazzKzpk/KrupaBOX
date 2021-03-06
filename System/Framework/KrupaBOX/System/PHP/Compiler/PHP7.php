<?php

namespace PHP\Compiler
{
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\StreamOutput;
    use Transphpile\IO\SymfonyIO;
    use Transphpile\Transpile\Transpile;

    class PHP7
    {
        protected static $__isInitialized = false;
        protected static function __isInitialized()
        {
            if (self::$__isInitialized == true) return null;

            \KrupaBOX\Internal\Library::load("PhpParser");
            \KrupaBOX\Internal\Library::load("Transphpile");

            self::$__isInitialized = true;
        }

        protected static $memoryStream = null;
        protected static $memoryIO     = null;

        protected static function getMemoryStream()
        {
            self::__isInitialized();
            if (self::$memoryStream != null) return self::$memoryStream;
            self::$memoryStream = fopen('php://memory', 'rw', false);
            return self::$memoryIO;
        }

        protected static function getMemoryIO()
        {
            self::__isInitialized();

            if (self::$memoryIO != null) return self::$memoryIO;
            self::getMemoryStream();

            $input = new ArrayInput(array());
            $stream = self::$memoryStream;
            $output = new StreamOutput($stream);

            self::$memoryIO = new SymfonyIO($input, $output);
            return self::$memoryIO;
        }

        public static function getCompiledCachePathToPHP5($path)
        {
            self::__isInitialized();

            $path = \File\Wrapper::parsePath($path);

            if (!stringEx($path)->startsWith(APPLICATION_FOLDER)) return null;
            if (!\File::exists($path)) return null;

            if (!stringEx($path)->startsWith(APPLICATION_FOLDER)) return null;
            if (!\File::exists($path)) return null;
            $lastModifiedDate = \File::getLastModifiedDateTimeEx($path);
            $lastModifiedDate = (($lastModifiedDate != null) ? $lastModifiedDate->toString() : (\DateTimeEx::now()->toString()));
            $lastModifiedDateHash = \Security\Hash::toSha1($lastModifiedDate);

            $namespacePath = stringEx($path)->subString(stringEx(APPLICATION_FOLDER)->length);
            $cachePath = (\Garbage\Cache::getCachePath() . ".krupabox/subset/" . $namespacePath . "/" . $lastModifiedDateHash . ".blob");

            return $cachePath;
        }

        public static function isCompiledToPHP5($path)
        {
            return \File::exists(self::getCompiledCachePathToPHP5($path));
        }

        public static function loadCompiledToPHP5($path)
        {
            $compiledPath = self::getCompiledCachePathToPHP5($path);
            if (\File::exists($compiledPath))
                return \Import::PHP($compiledPath);
            return false;
        }

        public static function compileToPHP5($path, $autoLoad = false)
        {
            $compiledPath = self::getCompiledCachePathToPHP5($path);
            if ($compiledPath == null) return null;

            if (\File::exists($compiledPath)) {
                if ($autoLoad == true)
                    return \Import::PHP($compiledPath);
                return true;
            }

            // Clean last compiled
            $compiledDirPath = \File::getDirectoryPath($compiledPath);
            if (\DirectoryEx::isDirectory($compiledDirPath)) {
                $compiledDirFiles = \DirectoryEx::listDirectory($compiledDirPath);
                if ($compiledDirFiles != null)
                    foreach ($compiledDirFiles as $compiledDirFile)
                        \File::delete($compiledDirPath . "/" . $compiledDirFile);
            }

            $input = new ArrayInput(array());
            $stream = fopen('php://memory', 'rw', false);
            $output = new StreamOutput($stream);

            $IO = new SymfonyIO($input, $output);

            $php7Data = \File::getContents($path);
            if (!stringEx($php7Data)->contains("<?php"))
                $php7Data = ("<?php \r\n" . $php7Data);

            $php7Data = self::parseScalarTyping($php7Data);
            $php7Data = stringEx($php7Data)->replace("<?php", "<?php declare(strict_types=1);");

            //$tmpPath = tempnam(sys_get_temp_dir(), 'php7to5');
            \File::setContents(\File\Wrapper::parsePath("cache://.krupabox/php7/folder.blob"), "_");
            $tmpPath = (\File\Wrapper::parsePath("cache://.krupabox/php7/") . \Security\Hash::toSha1($path . \Time::getCurrent(true)) . ".blob");
            file_put_contents($tmpPath, $php7Data);

            $transpiler = new Transpile($IO);
            $transpiler->transpile($tmpPath, '-');

            @unlink($tmpPath);
            @rewind($stream);

            $compiledPhp5 = stream_get_contents($stream);
            \File::setContents($compiledPath, "<?php \r\n" . $compiledPhp5);

            if ($autoLoad == true)
                return \Import::PHP($compiledPath);
            return true;
        }

        protected static function parseScalarTyping($phpString)
        {
//            $phpString = "<?php 1 < 2 > ! * + - +;";
////            $tokenizer = new \PHP\Interpreter\Tokenizer($phpString);
//            dump($tokenizer);
//            exit;

            $tokenizer = new \PHP\Interpreter\Tokenizer($phpString);

            $tokens = Arr();
            foreach ($tokenizer as $token)
                if ($token->typeSid != "T_WHITESPACE" && $token->typeSid != "T_COMMENT")
                    $tokens->add($token);


            for ($i = 0; $i < $tokens->count; $i++)
            {
                $token = $tokens[$i];

                if ($token->typeSid == "T_OBJECT_OPERATOR")
                {
                    if ($tokens->count > $i + 2)
                    {
                        if ($tokens[$i + 1]->typeSid == "T_STRING" && $tokens[$i + 2]->typeSid == "T_OPEN_ROUND")
                        {
                            // Find init
                            $j = $i;

                            $countParenteshes = 0;
                            $countSquares = 0;
                            for (; $j >= 0; $j--)
                            {
                                if ($tokens[$j]->typeSid == "T_OPEN_ROUND")
                                    $countParenteshes--;
                                elseif ($tokens[$j]->typeSid == "T_CLOSE_ROUND"){
                                    $countParenteshes++;
                                }

                                if ($tokens[$j]->typeSid == "T_OPEN_SQUARE")
                                    $countSquares--;
                                elseif ($tokens[$j]->typeSid == "T_CLOSE_SQUARE"){
                                    $countSquares++;
                                }

//                                dump($tokens[$j]);
//                                dump($countParenteshes);

                                if ($countParenteshes == 0 && $countSquares == 0)
                                {
//                                    dump($tokens[$j]);
                                    if ($tokens[$j]->typeSid == "T_VARIABLE")
                                    {
                                        if (($j - 1) > 0 && $tokens[$j - 1]->typeSid == "T_DOUBLE_COLON" &&
                                            ($j - 2) > 0 && $tokens[$j - 2]->typeSid == "T_STRING")
                                        {
                                            $removeScallars = ("" . $tokens[$j - 2]->content);
                                            while (stringEx($removeScallars)->startsWith("scalar("))
                                                $removeScallars = stringEx($removeScallars)->subString(7);
                                            if ($removeScallars == "self")
                                                $j = ($j - 2);
                                        }

//                                        if (isset($tokenizer[($token->id) - 1]) == false)
//                                        {
//                                            dump($token);
//                                            exit;
//
//                                        }

                                        $initToken = null;
                                        $endToken  = null;
                                        foreach ($tokenizer as $__token) if ($__token->id == $token->id)      { $initToken = $__token; break; }
                                        foreach ($tokenizer as $__token) if ($__token->id == $tokens[$j]->id) { $endToken = $__token; break; }
                                        if ($initToken != null && $endToken != null) {
                                            $initToken->content = (")" . $initToken->content);
                                            $endToken->content = ("scalar(" . $endToken->content);
                                        }
//                                        $tokenizer[($token->id) - 1]->content = (")" . $tokenizer[($token->id) - 1]->content);
//                                        $tokenizer[($tokens[$j]->id) - 1]->content = ("scalar(" . $tokenizer[($tokens[$j]->id) - 1]->content);

                                        break;
                                    }
                                    else
                                    {
//                                        dump($tokens[$j]);

                                        $isBreakCodeToken = (
                                            $tokens[$j]->typeSid == "T_SEMICOLON" || $tokens[$j]->typeSid == "T_QUESTION" ||
                                            $tokens[$j]->typeSid == "T_COLON" || $tokens[$j]->typeSid == "T_CLOSE_CURLY" ||
                                            $tokens[$j]->typeSid == "T_OPEN_CURLY" || $tokens[$j]->typeSid == "T_SLASH" ||
                                            $tokens[$j]->typeSid == "T_STAR" || $tokens[$j]->typeSid == "T_MINUS" ||
                                            $tokens[$j]->typeSid == "T_PLUS" || $tokens[$j]->typeSid == "T_DOT" ||
                                            $tokens[$j]->typeSid == "T_BOOLEAN_OR" || $tokens[$j]->typeSid == "T_IS_IDENTICAL" ||
                                            $tokens[$j]->typeSid == "T_IS_EQUAL" || $tokens[$j]->typeSid == "T_EQUAL" ||
                                            $tokens[$j]->typeSid == "T_BOOLEAN_AND" || $tokens[$j]->typeSid == "T_COMMA" ||
                                            $tokens[$j]->typeSid == "T_XOR_EQUAL" || $tokens[$j]->typeSid == "T_SR" ||
                                            $tokens[$j]->typeSid == "T_SL_EQUAL" || $tokens[$j]->typeSid == "T_SL" ||
                                            $tokens[$j]->typeSid == "T_POW_EQUAL" || $tokens[$j]->typeSid == "T_POW" ||
                                            $tokens[$j]->typeSid == "T_PLUS_EQUAL" || $tokens[$j]->typeSid == "T_OR_EQUAL" ||
                                            $tokens[$j]->typeSid == "T_MUL_EQUAL" || $tokens[$j]->typeSid == "T_MOD_EQUAL" ||
                                            $tokens[$j]->typeSid == "T_MINUS_EQUAL" || $tokens[$j]->typeSid == "T_LOGICAL_XOR" ||
                                            $tokens[$j]->typeSid == "T_LOGICAL_OR" || $tokens[$j]->typeSid == "T_LOGICAL_AND" ||
                                            $tokens[$j]->typeSid == "T_SPACESHIP" || $tokens[$j]->typeSid == "T_IS_SMALLER_OR_EQUAL" ||
                                            $tokens[$j]->typeSid == "T_IS_NOT_IDENTICAL" || $tokens[$j]->typeSid == "T_IS_NOT_EQUAL" ||
                                            $tokens[$j]->typeSid == "T_IS_IDENTICAL" || $tokens[$j]->typeSid == "T_IS_GREATER_OR_EQUAL	" ||
                                            $tokens[$j]->typeSid == "T_IS_EQUAL" || $tokens[$j]->typeSid == "T_INC" ||
                                            $tokens[$j]->typeSid == "T_DOUBLE_ARROW" || $tokens[$j]->typeSid == "T_DEC" ||
                                            $tokens[$j]->typeSid == "T_CONCAT_EQUAL" || $tokens[$j]->typeSid == "T_BOOLEAN_OR" ||
                                            $tokens[$j]->typeSid == "T_AND_EQUAL" || $tokens[$j]->typeSid == "T_EXCLAMATION" ||
                                            $tokens[$j]->typeSid == "T_LT" || $tokens[$j]->typeSid == "T_GT"
                                        );



                                        if ($isBreakCodeToken == false && $tokens[$j]->typeSid == "T_STRING")
                                        {
                                            $removeScallars = ("" . $tokens[$j]->content);
                                            while (stringEx($removeScallars)->startsWith("scalar("))
                                                $removeScallars = stringEx($removeScallars)->subString(7);
                                            if ($removeScallars == "self")
                                            {
                                                $isBreakCodeToken = true;
                                                $j = ($j - 1);
                                            }

                                        }

                                        if ($isBreakCodeToken == true)
                                        {
                                            $j = ($j + 1);

                                            $initToken = null;
                                            $endToken  = null;
                                            foreach ($tokenizer as $__token) if ($__token->id == $token->id)      { $initToken = $__token; break; }
                                            foreach ($tokenizer as $__token) if ($__token->id == $tokens[$j]->id) { $endToken = $__token; break; }
                                            if ($initToken != null && $endToken != null) {
                                                $initToken->content = (")" . $initToken->content);


                                                $parenthesesTokenFix = null;

                                                // Fix scalar after if/for/else/foreach/etc
                                                if ($endToken->typeSid == "T_IF" || $endToken->typeSid == "T_ELSE" || $endToken->typeSid == "T_RETURN" || $endToken->typeSid == "T_FOR" || $endToken->typeSid == "T_FOREACH" || $endToken->typeSid == "T_ELSEIF")
                                                {
                                                    if ($endToken->typeSid == "T_RETURN")
                                                        $parenthesesTokenFix = $endToken;
                                                    else
                                                    {
//                                                        $endToken->content = ($endToken->content . "scalar(");
                                                        $findToken = false;
                                                        foreach ($tokenizer as $__token)
                                                        {
                                                            if ($__token->id == $endToken->id)
                                                            { $findToken = true; continue; }

                                                            if ($findToken == true && $__token->typeSid == "T_OPEN_ROUND")
                                                            {
                                                                $parenthesesTokenFix = $__token;
                                                                break;
                                                            }
                                                        }
//
                                                    }
//
//                                                    dump($endToken);
//                                                    dump($tokens[$endToken->id]);
//                                                    exit;
//
//                                                    dump($isBreakCodeToken);
//                                                    dump($tokens[$j]->typeSid);
//                                                    dump($endToken->content);
//                                                    dump($endToken->typeSid);
//
//                                                    exit;
                                                }

                                                if ($parenthesesTokenFix == null)
                                                    $endToken->content = ("scalar(" . $endToken->content);
                                                else $parenthesesTokenFix->content = ($parenthesesTokenFix->content . " scalar(");

                                            }

//                                            $tokenizer[($token->id) - 1]->content = (")" . $tokenizer[($token->id) - 1]->content);
//                                            $tokenizer[($tokens[$j]->id) - 1]->content = ("scalar(" . $tokenizer[($tokens[$j]->id) - 1]->content);

                                            break;
                                        }
                                    }
                                }
                                elseif ($countParenteshes < 0)
                                {
                                    $j = ($j + 1);

                                    $initToken = null;
                                    $endToken  = null;
                                    foreach ($tokenizer as $__token) if ($__token->id == $token->id)      { $initToken = $__token; break; }
                                    foreach ($tokenizer as $__token) if ($__token->id == $tokens[$j]->id) { $endToken = $__token; break; }
                                    if ($initToken != null && $endToken != null) {
                                        $initToken->content = (")" . $initToken->content);
                                        $endToken->content = ("scalar(" . $endToken->content);
                                    }

//                                    $tokenizer[($token->id) - 1]->content = (")" . $tokenizer[($token->id) - 1]->content);
//                                    $tokenizer[($tokens[$j]->id) - 1]->content = ("scalar(" . $tokenizer[($tokens[$j]->id) - 1]->content);
                                    break;
                                }

                            }
                        }
                    }
                }
            }

            $preCode = "";
            foreach ($tokenizer as $token)
                $preCode .= $token->content;
            return $preCode;

            dump($preCode);

            exit;



            $preCode = "";
            foreach ($tokenizer as $token)
                $preCode .= $token->content;


               dump($preCode);

            exit;
        }
    }
}
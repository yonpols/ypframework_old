<?php
    class Configuration
    {
        public $application;
        public $mode;
        public $paths;
        public $routes;

        private $ordered_routes;
        private $databases;

        private static $instance = null;

        public static function get($configFileName = null)
        {
            if (self::$instance === null)
                self::$instance = new Configuration($configFileName);

            return self::$instance;
        }

        public function application($setting = null, $default = null)
        {
            if ($setting === null)
                return $this->application->{$this->mode};
            elseif (isset ($this->application->{$this->mode}->{$setting}))
                return $this->application->{$this->mode}->{$setting};

            return $default;
        }

        public function database($setting = null)
        {
            if (isset($this->databases->{$this->mode}))
            {
                if ($setting === null)
                    return $this->databases->{$this->mode};
                elseif (isset ($this->databases->{$this->mode}->{$setting}))
                    return $this->databases->{$this->mode}->{$setting};
            } else
                return null;
        }

        public function matchingRoute($action)
        {
            foreach ($this->ordered_routes as $route)
            {
                if (($match = $route->matches($action)) !== false)
                    return $match;
            }
            return false;
        }

        public function registerRoute(Route $route)
        {
            $name = $route->getName();

            if (isset($this->ordered_routes[$name]))
                return false;

            $this->ordered_routes[$name] = $route;
            $this->routes->{$name} = $route;
        }

        private function __construct($configFileName = null)
        {
            if ($configFileName === null)
                $configFileName = build_file_path(APP_PATH, 'config.yml');

            require_once build_file_path(LIB_PATH, 'sfYaml/sfYamlParser.php');
            $yaml = new sfYamlParser();

            try
            {
              $config = $yaml->parse(file_get_contents($configFileName));
            }
            catch (InvalidArgumentException $e)
            {
                throw new ErrorCorruptFile($configFileName, $e->getMessage());
            }

            $this->processConfig($config);
        }

        private function processConfig($config)
        {
            if (isset($config['application']))
            {
                $this->processConfigApplication ($config['application']);
                unset($config['application']);
            }
            else
                throw new ErrorCorruptFile('config.yml', "Error in configuration file: no 'application' section present");

            if (isset($config['routes']))
            {
                $this->processConfigRoutes ($config['routes']);
                unset($config['routes']);
            }
            else
                throw new ErrorCorruptFile ('config.yml', "Error in configuration file: no 'routes' section present");

            if (isset($config['databases']))
            {
                $this->processConfigDataBases ($config['databases']);
                unset($config['databases']);
            }

            $this->paths = new Object();
            $this->paths->base = realpath(BASE_PATH);
            $this->paths->ypf = realpath(YPF_PATH);
            $this->paths->library = realpath(LIB_PATH);
            $this->paths->www = realpath(WWW_PATH);
            $this->paths->application = realpath(APP_PATH);
            $this->paths->log = realpath(LOG_PATH);
            $this->paths->temp = realpath(TMP_PATH);
            $this->paths->request_uri = sprintf('http%s://%s%s%s', (isset($_SERVER['HTTPS'])?'s':''), $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'],
                                        ($_SERVER['QUERY_STRING']!='')? '?'.$_SERVER['QUERY_STRING']: '');

            if (isset($config['paths']))
            {
                foreach ($config['paths'] as $name=>$value)
                    if (!isset($this->paths->{$name}))
                        $this->paths->{$name} = $this->objetize($value);
                unset($config['paths']);
            }

            foreach ($config as $name=>$value)
                if (!isset($this->{$name}))
                    $this->{$name} = $this->objetize($value);

            $this->replace($this->paths);
            $this->replace($this->application);
            $this->replace($this->ordered_routes);
            $this->replace($this->databases);

            foreach ($config as $name=>$value)
                $this->replace($this->{$name});

            if ($this->mode === null | !isset ($this->application->{$this->mode}))
            {
                $first = null;
                foreach ($this->application as $name=>$mode)
                {
                    if ($first === null) $first = $name;

                    if (stripos($this->paths->request_uri, $mode->url) === 0)
                    {
                        $this->mode = $name;
                        break;
                    }
                }

                if ($this->mode === null)
                    $this->mode = isset($this->application->production)? 'production': $first;
            }

            $this->paths->base_url = $this->application('url');
        }

        private function processConfigApplication($config)
        {
            if (array_key_exists('mode', $config))
            {
                $this->mode = $config['mode'];
                unset($config['mode']);
            }

            $this->application = $this->objetize($config);
        }

        private function processConfigRoutes($config)
        {
            $this->ordered_routes = array();
            $this->routes = new Object();

            foreach($config as $name=>$route)
            {
                $this->ordered_routes[$name] = new Route($name, $route);
                $this->routes->{$name} = $this->ordered_routes[$name];
            }
        }

        private function processConfigDataBases($config)
        {
            $this->databases = $this->objetize($config);
        }

        private function objetize($config)
        {
            if (is_array($config))
            {
                $key = array_keys($config);

                if (empty($key) | (is_numeric($key[0])))
                {
                    $object = array();
                    foreach ($config as $val)
                        $object[] = $this->objetize ($val);
                    return $object;
                } else
                {
                    $object = new Object();
                    foreach ($config as $key=>$val)
                        $object->{$key} = $this->objetize ($val);
                    return $object;
                }
            } else
                return $config;
        }

        private function replace(&$object)
        {
            if (is_object($object))
                foreach($object as $key=>$val)
                    $this->replace($object->{$key});

            elseif (is_array($object))
                foreach($object as $key=>$val)
                    $this->replace($object[$key]);

            elseif (is_string($object))
                $object = $this->processString ($object);
        }

        private function processString($value)
        {
            while (preg_match('/\\{%([a-z][a-zA-Z0-9_\\.]+)\\}/', $value, $matches, PREG_OFFSET_CAPTURE))
            {
                $replaced = '$this->'.str_replace('.', '->', $matches[1][0]);
                $replaced = eval("return $replaced;");
                $value = substr($value, 0, $matches[0][1]).$replaced.substr($value, $matches[0][1]+strlen($matches[0][0]));
            }

            return $value;
        }
    }
?>

<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Environment;

use Titon\Common\Mixin\Configurable;
use Titon\Common\Mixin\FactoryAware;
use Titon\Environment\Exception\MissingBootstrapException;
use Titon\Environment\Exception\MissingHostException;
use Titon\Event\Emittable;
use Titon\Utility\Path;

/**
 * A hub that allows you to store different environment host configurations,
 * which can be detected and initialized on runtime.
 *
 * @package Titon\Environment
 * @events
 *      env.onInit(Environment $env, Host $host)
 *      env.onBootstrap(Environment $env, Host $host)
 *      env.onFallback(Environment $env, Host $host)
 */
class Environment {
    use Configurable, Emittable, FactoryAware;

    /**
     * Types of environments.
     */
    const DEV = 'dev';
    const DEVELOPMENT = 'dev';
    const STAGING = 'staging';
    const PROD = 'prod';
    const PRODUCTION = 'prod';
    const QA = 'qa';

    /**
     * Default configuration.
     *
     * @type array
     */
    protected $_config = [
        'bootstrapPath' => '',
        'throwMissingError' => true
    ];

    /**
     * Currently active environment.
     *
     * @type \Titon\Environment\Host
     */
    protected $_current;

    /**
     * List of all environments.
     *
     * @type \Titon\Environment\Host[]
     */
    protected $_hosts = [];

    /**
     * The fallback environment.
     *
     * @type \Titon\Environment\Host
     */
    protected $_fallback;

    /**
     * Apply configuration.
     *
     * @param array $config
     */
    public function __construct(array $config = []) {
        $this->applyConfig($config);
    }

    /**
     * Add an environment host and setup the host mapping and fallback.
     *
     * @param string $key
     * @param \Titon\Environment\Host $host
     * @return \Titon\Environment\Host
     */
    public function addHost($key, Host $host) {
        $host->setKey($key);

        // Auto-set bootstrap path
        if ($path = $this->getConfig('bootstrapPath')) {
            $host->setBootstrap(Path::ds($path, true) . $key . '.php');
        }

        $this->_hosts[$key] = $host;

        // Set fallback if empty
        if (!$this->_fallback) {
            $this->setFallback($key);
        }

        return $host;
    }

    /**
     * Return the current environment.
     *
     * @return \Titon\Environment\Host
     */
    public function current() {
        return $this->_current;
    }

    /**
     * Return the fallback environment.
     *
     * @return \Titon\Environment\Host
     */
    public function getFallback() {
        return $this->_fallback;
    }

    /**
     * Return a host by key.
     *
     * @param string $key
     * @return \Titon\Environment\Host
     * @throws \Titon\Environment\Exception\MissingHostException
     */
    public function getHost($key) {
        if (isset($this->_hosts[$key])) {
            return $this->_hosts[$key];
        }

        throw new MissingHostException(sprintf('Environment host %s does not exist', $key));
    }

    /**
     * Returns the list of environments.
     *
     * @return \Titon\Environment\Host[]
     */
    public function getHosts() {
        return $this->_hosts;
    }

    /**
     * Initialize the environment by including the configuration.
     *
     * @throws \Titon\Environment\Exception\MissingBootstrapException
     */
    public function initialize() {
        if (!$this->_hosts) {
            return;
        }

        // Match a host to the machine hostname
        foreach ($this->getHosts() as $host) {
            foreach ($host->getHosts() as $name) {
                if ($this->isMachine($name)) {
                    $this->_current = $host;
                    break 2;
                }
            }
        }

        // If no environment found, use the fallback
        if (!$this->_current) {
            $this->_current = $this->_fallback;
        }

        $current = $this->current();

        $this->emit('env.onInit', [$this, $current]);

        // Bootstrap environment configuration
        if ($bootstrap = $current->getBootstrap()) {
            if (file_exists($bootstrap)) {
                include $bootstrap;

                $this->emit('env.onBootstrap', [$this, $current]);

            } else if ($this->getConfig('throwMissingError')) {
                throw new MissingBootstrapException(sprintf('Environment bootstrap for %s does not exist', $current->getKey()));
            }
        }
    }

    /**
     * Does the current environment match the passed key?
     *
     * @param string $key
     * @return bool
     */
    public function is($key) {
        return ($this->current() === $this->getHost($key));
    }

    /**
     * Determine if the name matches the host machine name.
     *
     * @param string $name
     * @return bool
     */
    public function isMachine($name) {
        $name = preg_quote($name, '/');

        // Allow for wildcards
        $name = str_replace('\*', '(.*?)', $name);

        return (bool) preg_match('/^' . $name . '/i', gethostname());
    }

    /**
     * Is the current environment on a localhost?
     *
     * @return bool
     */
    public function isLocalhost() {
        return (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $_SERVER['HTTP_HOST'] === 'localhost');
    }

    /**
     * Is the current environment development?
     *
     * @return bool
     */
    public function isDevelopment() {
        return $this->current()->isDevelopment();
    }

    /**
     * Is the current environment production?
     *
     * @return bool
     */
    public function isProduction() {
        return $this->current()->isProduction();
    }

    /**
     * Is the current environment QA?
     *
     * @return bool
     */
    public function isQA() {
        return $this->current()->isQA();
    }

    /**
     * Is the current environment staging?
     *
     * @return bool
     */
    public function isStaging() {
        return $this->current()->isStaging();
    }

    /**
     * Set the fallback environment; fallback must exist before hand.
     *
     * @param string $key
     * @return $this
     */
    public function setFallback($key) {
        $this->_fallback = $this->getHost($key);

        $this->emit('env.onFallback', [$this, $this->_fallback]);

        return $this;
    }

}
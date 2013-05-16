<?php
namespace ValuResource\Service;

use ValuResource\Service\Exception\UnsupportedNamespaceException;

use ValuSo\Exception\UnsupportedOperationException;
use ValuSo\Command\CommandInterface;
use ValuSo\Feature;
use ValuSo\Feature\ServiceBrokerTrait;
use ValuSo\Feature\OptionsTrait;

class ProxyService
    implements Feature\ServiceBrokerAwareInterface,
               Feature\ConfigurableInterface
{
    use ServiceBrokerTrait;
    use OptionsTrait;
    
    protected $optionsClass = 'ValuResource\Service\ProxyServiceOptions';
    
    public function __invoke(CommandInterface $command)
    {
        $ns = $command->getParam('ns', 0);
        $this->assertNamespace($ns);
        
        switch ($command->getOperation()) {
            case 'exists':
            case 'remove':
                return $this->proxy(
                    $command, 
                    $ns,
                    ['resourceId']);
                break;
            case 'findBy':
            case 'findManyBy':
                return $this->proxy(
                    $command,
                    $ns,
                    ['property', 'value', 'specs']);
                break;
            case 'create':
                return $this->proxy(
                    $command,
                    $ns,
                    ['specs']);
                    break;
            case 'update':
                return $this->proxy(
                    $command,
                    $ns,
                    ['resourceId', 'specs']);
                break;
        }
    }
    
    /**
     * Proxy command
     * 
     * @param CommandInterface $command
     * @param string $ns
     * @param array $args
     */
    protected function proxy(CommandInterface $command, $ns, $args)
    {
        $arguments = [];
        foreach ($args as $key => $arg) {
            if ($command->hasParam($arg)) {
                $arguments[$arg] = $command->getParam($arg);
            } elseif ($command->hasParam($key+1)) {
                $arguments[$arg] = $command->getParam($key+1);
            }
        }
        
        $this->mapArguments($arguments, $ns);
        
        if (!$this->mapCommand($command, $ns)) {
            throw new UnsupportedOperationException(
                "Resource service for namespace %NS% doesn't support operation %OPERATION%",
                array('NS' => $ns, 'OPERATION' => $command->getOperation()));
        }
        
        return $this->getServiceBroker()->dispatch($command)->first();
    }
    
    protected function mapArguments(&$arguments, $ns)
    {
        if (array_key_exists('property', $arguments)) {
            $arguments['property'] = $this->mapSpec($arguments['property']);
        } elseif (array_key_exists('specs', $arguments)) {
            if (is_string($arguments['specs'])) {
                $arguments['specs'] = $this->mapSpec($arguments['property']);
            } elseif (is_array($arguments['specs'])) {
                foreach ($arguments['specs'] as $key => $value) {
                    $arguments['specs'][$key] = $this->mapSpec($value);
                }
            }
        } elseif (array_key_exists('resourceId', $arguments)) {
            $arguments[$this->mapSpec('resourceId')] = $arguments['resourceId'];
        }
    }
    
    /**
     * Maps command for proxying
     * 
     * @param CommandInterface $command
     * @param string $ns
     */
    protected function mapCommand(CommandInterface $command, $ns)
    {
        $map    = $this->getOption('map');
        $nsKey  = $ns.'::'.$command->getOperation();
        $key    = $command->getOperation();
        
        if (array_key_exists($nsKey, $map)) {
            $mapped = $map[$nsKey];
        } elseif (array_key_exists($key, $map)) {
            $mapped = $map[$key];
        }
        
        if (!$mapped) {
            return false;
        } else {
            list($service, $operation) = explode('.', $mapped);

            $command->setService($service);
            $command->setOperation($operation);
            
            return true;
        }
    }
    
    /**
     * Assert that namespace is supported
     * 
     * @throws UnsupportedNamespaceException
     */
    protected function assertNamespace($ns)
    {
        if ($ns === '*') {
            return;
        }
        
        $namespaces = $this->getOption('namespaces');
        if (!in_array($ns, $namespaces)) {
            throw new UnsupportedNamespaceException(
                'Namespace %NS% is not supported', ['NS' => $ns]);
        }
    }
}
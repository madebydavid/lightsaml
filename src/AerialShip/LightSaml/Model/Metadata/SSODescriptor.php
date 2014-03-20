<?php

namespace AerialShip\LightSaml\Model\Metadata;

use AerialShip\LightSaml\Bindings;
use AerialShip\LightSaml\Error\InvalidXmlException;
use AerialShip\LightSaml\Helper;
use AerialShip\LightSaml\Meta\GetXmlInterface;
use AerialShip\LightSaml\Meta\LoadFromXmlInterface;
use AerialShip\LightSaml\Meta\SerializationContext;
use AerialShip\LightSaml\Meta\XmlChildrenLoaderTrait;
use AerialShip\LightSaml\Model\Metadata\Service\AbstractService;
use AerialShip\LightSaml\Model\Metadata\Service\AssertionConsumerService;
use AerialShip\LightSaml\Model\Metadata\Service\SingleLogoutService;
use AerialShip\LightSaml\Protocol;


abstract class SSODescriptor implements GetXmlInterface, LoadFromXmlInterface
{


    /** @var AbstractService[] */
    protected $services;

    /** @var KeyDescriptor[] */
    protected $keyDescriptors;




    function __construct(array $services = null, array $keyDescriptors = null) {
        $this->services = $services ?: array();
        $this->keyDescriptors = $keyDescriptors ?: array();
    }

protected function iterateChildrenElements(\DOMElement $xml, \Closure $elementCallback) {
        for ($node = $xml->firstChild; $node !== NULL; $node = $node->nextSibling) {
            if ($node instanceof \DOMElement) {
                $elementCallback($node);
            }
        }
    }

    protected function loadXmlChildren(\DOMElement $xml, array $node2ClassMap, \Closure $itemCallback) {
        $result = array();
        $this->iterateChildrenElements($xml, function(\DOMElement $node) use (&$result, $node2ClassMap, $itemCallback) {
            $recognized = $this->doMapping($node, $node2ClassMap, $itemCallback);
            if (!$recognized) {
                $result[] = $node;
            }
        });
        return $result;
    }


    /**
     * @param \DOMElement $node
     * @param array $node2ClassMap
     * @param callable $itemCallback
     * @return \DOMElement|null
     */
    private function doMapping(\DOMElement $node, array $node2ClassMap, \Closure $itemCallback) {
        $recognized = false;
        foreach ($node2ClassMap as $meta) {
            if (!$meta) continue;
            $this->getNodeNameAndNamespaceFromMeta($meta, $nodeName, $nodeNS);
            if ($nodeName == $node->localName
                && (!$nodeNS || $nodeNS == $node->namespaceURI)
            ) {
                $obj = $this->getObjectFromMetaClass($meta, $node);
                $itemCallback($obj);
                $recognized = true;
                break;
            }
        } // foreach $node2ClassMap
        return $recognized;
    }


    private function getNodeNameAndNamespaceFromMeta($meta, &$nodeName, &$nodeNS) {
        if (!is_array($meta)) {
            throw new \InvalidArgumentException('Meta must be array');
        }
        if (!isset($meta['node'])) {
            throw new \InvalidArgumentException('Missing node meta');
        }
        $nodeName = null;
        $nodeNS = null;
        if (is_string($meta['node'])) {
            $nodeName = $meta['node'];
        } else if (is_array($meta['node'])) {
            $nodeName = @$meta['node']['name'];
            $nodeNS = @$meta['node']['ns'];
        }
        if (!$nodeName) {
            throw new \InvalidArgumentException('Missing node name meta');
        }
    }

    /**
     * @param $meta
     * @param \DOMElement $node
     * @throws \InvalidArgumentException
     * @return LoadFromXmlInterface
     */
    private function getObjectFromMetaClass($meta, \DOMElement $node) {
        $class = @$meta['class'];
        if (!$class) {
            throw new \InvalidArgumentException('Missing class meta');
        }
        $obj = new $class();
        if ($obj instanceof LoadFromXmlInterface) {
            $obj->loadFromXml($node);
        } else {
            throw new \InvalidArgumentException("Class $class must implement LoadFromXmlInterface");
        }
        return $obj;
    }




    /**
     * @return KeyDescriptor[]
     */
    public function getKeyDescriptors() {
        return $this->keyDescriptors;
    }

    /**
     * @param KeyDescriptor[] $keyDescriptors
     */
    public function setKeyDescriptors(array $keyDescriptors) {
        $this->keyDescriptors = $keyDescriptors;
    }


    /**
     * @param KeyDescriptor $keyDescriptor
     */
    public function addKeyDescriptor(KeyDescriptor $keyDescriptor) {
        $this->keyDescriptors[] = $keyDescriptor;
    }

    /**
     * @param AbstractService[] $services
     */
    public function setServices(array $services) {
        $this->services = $services;
    }

    /**
     * @return AbstractService[]
     */
    public function getServices() {
        return $this->services;
    }

    /**
     * @param AbstractService $service
     * @return SpSsoDescriptor
     */
    public function addService(AbstractService $service) {
        $this->services[] = $service;
        return $this;
    }


    /**
     * @return string[]
     */
    public function getSupportedProtocols() {
        $arr = array();
        foreach ($this->getServices() as $service) {
            $protocol = Bindings::getBindingProtocol($service->getBinding());
            $arr[$protocol] = $protocol;
        }
        return array_values($arr);
    }

    /**
     * @return string
     */
    public function getProtocolSupportEnumeration() {
        return join(' ', $this->getSupportedProtocols());
    }


    /**
     * @param string|null $use
     * @return KeyDescriptor[]
     */
    function findKeyDescriptors($use) {
        $result = array();
        foreach ($this->getKeyDescriptors() as $kd) {
            if ($use === null || !$kd->getUse() || $kd->getUse() == $use) {
                $result[] = $kd;
            }
        }
        return $result;
    }

    /**
     * @param string $class
     * @param string|null $binding
     * @return AbstractService[]
     */
    function findServices($class, $binding) {
        $result = array();
        foreach ($this->getServices() as $service) {
            if (Helper::doClassNameMatch($service, $class)) {
                if (!$binding || $binding == $service->getBinding()) {
                    $result[] = $service;
                }
            }
        }
        return $result;
    }

    /**
     * @param string|null $binding
     * @return SingleLogoutService[]
     */
    public function findSingleLogoutServices($binding = null) {
        return $this->findServices('AerialShip\LightSaml\Model\Metadata\Service\SingleLogoutService', $binding);
    }

    /**
     * @param string|null $binding
     * @return AssertionConsumerService[]
     */
    public function findAssertionConsumerServices($binding = null) {
        return $this->findServices('AerialShip\LightSaml\Model\Metadata\Service\AssertionConsumerService', $binding);
    }

    /**
     * @param string|null $binding
     * @return Service\AbstractService[]
     */
    public function findSingleSignOnServices($binding = null) {
        return $this->findServices('AerialShip\LightSaml\Model\Metadata\Service\SingleSignOnService', $binding);
    }


    /**
     * @return string
     */
    abstract public function getXmlNodeName();


    /**
     * @param \DOMNode $parent
     * @param \AerialShip\LightSaml\Meta\SerializationContext $context
     * @return \DOMElement
     */
    function getXml(\DOMNode $parent, SerializationContext $context) {
        $result = $context->getDocument()->createElementNS(Protocol::NS_METADATA, 'md:'.$this->getXmlNodeName());
        $parent->appendChild($result);
        $result->setAttribute('protocolSupportEnumeration', $this->getProtocolSupportEnumeration());
        foreach ($this->getKeyDescriptors() as $kd) {
            $kd->getXml($result, $context);
        }
        foreach ($this->getServices() as $service) {
            $service->getXml($result, $context);
        }
        return $result;
    }


    /**
     * @param \DOMElement $xml
     * @throws \AerialShip\LightSaml\Error\InvalidXmlException
     */
    function loadFromXml(\DOMElement $xml) {
        $name = $this->getXmlNodeName();
        if ($xml->localName != $name || $xml->namespaceURI != Protocol::NS_METADATA) {
            throw new InvalidXmlException("Expected $name element and ".Protocol::NS_METADATA.' namespace but got '.$xml->localName);
        }

        $this->loadXmlChildren(
            $xml,
            array(
                array(
                    'node' => array('name'=>'SingleLogoutService', 'ns'=>Protocol::NS_METADATA),
                    'class' => '\AerialShip\LightSaml\Model\Metadata\Service\SingleLogoutService'
                ),
                array(
                    'node' => array('name'=>'SingleSignOnService', 'ns'=>Protocol::NS_METADATA),
                    'class' => '\AerialShip\LightSaml\Model\Metadata\Service\SingleSignOnService'
                ),
                array(
                    'node' => array('name'=>'AssertionConsumerService', 'ns'=>Protocol::NS_METADATA),
                    'class' => '\AerialShip\LightSaml\Model\Metadata\Service\AssertionConsumerService'
                ),
                array(
                    'node' => array('name'=>'KeyDescriptor', 'ns'=>Protocol::NS_METADATA),
                    'class' => '\AerialShip\LightSaml\Model\Metadata\KeyDescriptor'
                ),
            ),
            function(LoadFromXmlInterface $obj) {
                if ($obj instanceof AbstractService) {
                    $this->addService($obj);
                } else if ($obj instanceof KeyDescriptor) {
                    $this->addKeyDescriptor($obj);
                } else {
                    throw new \InvalidArgumentException('Invalid item type '.get_class($obj));
                }
            }
        );
    }
}

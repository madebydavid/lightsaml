<?php

namespace AerialShip\LightSaml\Model\Protocol;

use AerialShip\LightSaml\Error\InvalidXmlException;
use AerialShip\LightSaml\Meta\GetXmlInterface;
use AerialShip\LightSaml\Meta\LoadFromXmlInterface;
use AerialShip\LightSaml\Meta\SerializationContext;
use AerialShip\LightSaml\Meta\XmlChildrenLoaderTrait;
use AerialShip\LightSaml\Protocol;

class StatusCode implements GetXmlInterface, LoadFromXmlInterface
{
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


    /** @var  string */
    protected $value;

    /** @var  StatusCode|null */
    protected $child;



    /**
     * @param string $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @param \AerialShip\LightSaml\Model\Protocol\StatusCode|null $child
     */
    public function setChild($child) {
        $this->child = $child;
    }

    /**
     * @return \AerialShip\LightSaml\Model\Protocol\StatusCode|null
     */
    public function getChild() {
        return $this->child;
    }



    protected function prepareForXml() {
        if (!$this->getValue()) {
            throw new InvalidXmlException('StatusCode value not set');
        }
    }


    /**
     * @param \DOMNode $parent
     * @param SerializationContext $context
     * @return \DOMElement
     */
    function getXml(\DOMNode $parent, SerializationContext $context) {
        $this->prepareForXml();

        $result = $context->getDocument()->createElementNS(Protocol::SAML2, 'samlp:StatusCode');
        $result->setAttribute('Value', $this->getValue());

        if ($this->getChild()) {
            $this->getChild()->getXml($result, $context);
        }

        return $result;
    }

    /**
     * @param \DOMElement $xml
     * @throws \AerialShip\LightSaml\Error\InvalidXmlException
     * @return void
     */
    function loadFromXml(\DOMElement $xml) {
        if ($xml->localName != 'StatusCode' || $xml->namespaceURI != Protocol::SAML2) {
            throw new InvalidXmlException('Expected StatusCode element but got '.$xml->localName);
        }

        if (!$xml->hasAttribute('Value')) {
            throw new InvalidXmlException('Required attribute StatusCode Value missing');
        }
        $this->setValue($xml->getAttribute('Value'));

        $this->iterateChildrenElements($xml, function(\DOMElement $node) {
            if ($node->localName == 'StatusCode' && $node->namespaceURI == Protocol::SAML2) {
                $this->setChild(new StatusCode());
                $this->getChild()->loadFromXml($node);
            } else {
                throw new InvalidXmlException('Unknown element '.$node->localName);
            }
        });
    }


}

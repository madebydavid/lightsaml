<?php

namespace AerialShip\LightSaml\Model\Protocol;

use AerialShip\LightSaml\Error\InvalidXmlException;
use AerialShip\LightSaml\Meta\GetXmlInterface;
use AerialShip\LightSaml\Meta\LoadFromXmlInterface;
use AerialShip\LightSaml\Meta\SerializationContext;
use AerialShip\LightSaml\Meta\XmlChildrenLoaderTrait;
use AerialShip\LightSaml\Protocol;


class Status implements GetXmlInterface, LoadFromXmlInterface
{

    /** @var  StatusCode */
    protected $statusCode;

    /** @var string */
    protected $message;

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
     * @param \AerialShip\LightSaml\Model\Protocol\StatusCode $statusCode
     */
    public function setStatusCode($statusCode) {
        $this->statusCode = $statusCode;
    }

    /**
     * @return \AerialShip\LightSaml\Model\Protocol\StatusCode
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @param string $message
     */
    public function setMessage($message) {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getMessage() {
        return $this->message;
    }



    public function isSuccess() {
        $result = $this->getStatusCode() && $this->getStatusCode()->getValue() == Protocol::STATUS_SUCCESS;
        return $result;
    }


    public function setSuccess() {
        $this->setStatusCode(new StatusCode());
        $this->getStatusCode()->setValue(Protocol::STATUS_SUCCESS);
    }



    protected function prepareForXml() {
        if (!$this->getStatusCode()) {
            throw new InvalidXmlException('StatusCode not set');
        }
    }


    /**
     * @param \DOMNode $parent
     * @param \AerialShip\LightSaml\Meta\SerializationContext $context
     * @return \DOMElement
     */
    function getXml(\DOMNode $parent, SerializationContext $context) {
        $this->prepareForXml();

        $result = $context->getDocument()->createElementNS(Protocol::SAML2, 'samlp:Status');
        $parent->appendChild($result);

        $this->getStatusCode()->getXml($result, $context);

        if ($this->getMessage()) {
            $statusMessageNode = $context->getDocument()->createElementNS(Protocol::SAML2, 'samlp:StatusMessage', $this->getMessage());
            $result->appendChild($statusMessageNode);
        }

        return $result;
    }


    /**
     * @param \DOMElement $xml
     * @throws \AerialShip\LightSaml\Error\InvalidXmlException
     */
    function loadFromXml(\DOMElement $xml) {
        if ($xml->localName != 'Status' || $xml->namespaceURI != Protocol::SAML2) {
            throw new InvalidXmlException('Expected Status element but got '.$xml->localName);
        }

        $this->iterateChildrenElements($xml, function(\DOMElement $node) {
            if ($node->localName == 'StatusCode' && $node->namespaceURI == Protocol::SAML2) {
                $statusCode = new StatusCode();
                $statusCode->loadFromXml($node);
                $this->setStatusCode($statusCode);
            } else if ($node->localName == 'StatusMessage' && $node->namespaceURI == Protocol::SAML2) {
                $this->setMessage($node->textContent);
            }
        });

        if (!$this->getStatusCode()) {
            throw new InvalidXmlException('Missing StatusCode node');
        }
    }

}

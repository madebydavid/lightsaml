<?php

namespace AerialShip\LightSaml\Model\Assertion;

use AerialShip\LightSaml\Error\InvalidSubjectException;
use AerialShip\LightSaml\Error\InvalidXmlException;
use AerialShip\LightSaml\Meta\GetXmlInterface;
use AerialShip\LightSaml\Meta\LoadFromXmlInterface;
use AerialShip\LightSaml\Meta\SerializationContext;
use AerialShip\LightSaml\Meta\XmlChildrenLoaderTrait;
use AerialShip\LightSaml\Protocol;


class SubjectConfirmation implements GetXmlInterface, LoadFromXmlInterface
{


    /** @var string */
    protected $method;

    /** @var NameID */
    protected $nameID;

    /** @var SubjectConfirmationData */
    protected $data;


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
     * @param string $method
     */
    public function setMethod($method) {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @param NameID $nameID
     */
    public function setNameID($nameID) {
        $this->nameID = $nameID;
    }

    /**
     * @return NameID
     */
    public function getNameID() {
        return $this->nameID;
    }

    /**
     * @param SubjectConfirmationData $data
     */
    public function setData($data) {
        $this->data = $data;
    }

    /**
     * @return SubjectConfirmationData
     */
    public function getData() {
        return $this->data;
    }






    protected function prepareForXml() {
        if (!$this->getMethod()) {
            throw new InvalidSubjectException('No SubjectConfirmation Method set');
        }
        if (!$this->getData()) {
            throw new InvalidSubjectException('No SubjectConfirmationData set');
        }
    }


    /**
     * @param \DOMNode $parent
     * @param \AerialShip\LightSaml\Meta\SerializationContext $context
     * @return \DOMElement
     */
    function getXml(\DOMNode $parent, SerializationContext $context) {
        $this->prepareForXml();

        $result = $context->getDocument()->createElement('SubjectConfirmation');
        $parent->appendChild($result);

        $result->setAttribute('Method', $this->getMethod());

        $this->getNameID()->getXml($result, $context);

        $this->getData()->getXml($result, $context);

        return $result;
    }

    /**
     * @param \DOMElement $xml
     * @throws \LogicException
     * @throws \AerialShip\LightSaml\Error\InvalidXmlException
     */
    function loadFromXml(\DOMElement $xml) {
        if ($xml->localName != 'SubjectConfirmation' || $xml->namespaceURI != Protocol::NS_ASSERTION) {
            throw new InvalidXmlException('Expected Subject element but got '.$xml->localName);
        }

        if (!$xml->hasAttribute('Method')) {
            throw new InvalidXmlException('Missing Method attribute in SubjectConfirmation');
        }
        $this->setMethod($xml->getAttribute('Method'));


        $this->nameID = null;
        $this->loadXmlChildren(
            $xml,
            array(
                array(
                    'node' => array('name'=>'NameID', 'ns'=>Protocol::NS_ASSERTION),
                    'class' => '\AerialShip\LightSaml\Model\Assertion\NameID'
                ),
                array(
                    'node' => array('name'=>'SubjectConfirmationData', 'ns'=>Protocol::NS_ASSERTION),
                    'class' => '\AerialShip\LightSaml\Model\Assertion\SubjectConfirmationData'
                )
            ),
            function ($obj) {
                if ($obj instanceof NameID) {
                    if ($this->getNameID()) {
                        throw new InvalidXmlException('More than one NameID in SubjectConfirmation');
                    }
                    $this->setNameID($obj);
                } else if ($obj instanceof SubjectConfirmationData) {
                    $this->setData($obj);
                } else {
                    throw new \LogicException('Unexpected type '.get_class($obj));
                }
            }
        );
    }

}

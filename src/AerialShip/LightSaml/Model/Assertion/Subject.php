<?php

namespace AerialShip\LightSaml\Model\Assertion;

use AerialShip\LightSaml\Error\InvalidSubjectException;
use AerialShip\LightSaml\Error\InvalidXmlException;
use AerialShip\LightSaml\Meta\GetXmlInterface;
use AerialShip\LightSaml\Meta\LoadFromXmlInterface;
use AerialShip\LightSaml\Meta\SerializationContext;
use AerialShip\LightSaml\Meta\XmlChildrenLoaderTrait;
use AerialShip\LightSaml\Protocol;


class Subject implements GetXmlInterface, LoadFromXmlInterface
{
    use XmlChildrenLoaderTrait;


    /** @var NameID */
    protected $nameID;

    /** @var SubjectConfirmation[] */
    protected $subjectConfirmations = array();




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
     * @param SubjectConfirmation $subjectConfirmation
     */
    public function addSubjectConfirmation($subjectConfirmation) {
        $this->subjectConfirmations[] = $subjectConfirmation;
    }

    /**
     * @return SubjectConfirmation[]
     */
    public function getSubjectConfirmations() {
        return $this->subjectConfirmations;
    }



    protected function prepareForXml()
    {
        if (!$this->getNameID()) {
            throw new InvalidSubjectException('No NameID set');
        }
    }


    /**
     * @param \DOMNode $parent
     * @param \AerialShip\LightSaml\Meta\SerializationContext $context
     * @return \DOMElement
     */
    function getXml(\DOMNode $parent, SerializationContext $context)
    {
        $this->prepareForXml();

        $result = $context->getDocument()->createElement('Subject');
        $parent->appendChild($result);

        $this->getNameID()->getXml($result, $context);

        foreach ($this->getSubjectConfirmations() as $sc) {
            $sc->getXml($result, $context);
        }

        return $result;
    }

    /**
     * @param \DOMElement $xml
     * @throws \LogicException
     * @throws \AerialShip\LightSaml\Error\InvalidXmlException
     */
    function loadFromXml(\DOMElement $xml)
    {
        if ($xml->localName != 'Subject' || $xml->namespaceURI != Protocol::NS_ASSERTION) {
            throw new InvalidXmlException('Expected Subject element but got '.$xml->localName);
        }

        $this->nameID = null;
        $this->subjectConfirmations = array();

        $this->loadXmlChildren(
            $xml,
            array(
                array(
                    'node' => array('name'=>'NameID', 'ns'=>Protocol::NS_ASSERTION),
                    'class' => '\AerialShip\LightSaml\Model\Assertion\NameID'
                ),
                array(
                    'node' => array('name'=>'SubjectConfirmation', 'ns'=>Protocol::NS_ASSERTION),
                    'class' => '\AerialShip\LightSaml\Model\Assertion\SubjectConfirmation'
                )
            ),
            function ($object) {
                $this->loadXmlCallback($object);
            }
        );
        if (!$this->getNameID()) {
            throw new InvalidXmlException('Missing NameID element in Subject');
        }
        if (!$this->getSubjectConfirmations()) {
            /* for some reason my client's Idp does not provide this element - hacking it out */
            //throw new InvalidXmlException('Missing SubjectConfirmation element in Subject');
        }
    }


    protected function loadXmlCallback($object)
    {
        if ($object instanceof NameID) {
            if ($this->getNameID()) {
                throw new InvalidXmlException('More than one NameID in Subject');
            }
            $this->setNameID($object);
        } else if ($object instanceof SubjectConfirmation) {
            $this->addSubjectConfirmation($object);
        } else {
            throw new \LogicException('Unexpected type '.get_class($object));
        }
    }

}

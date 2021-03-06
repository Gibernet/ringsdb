<?php

namespace AppBundle\Model;

/**
 * Interface for a collection of SlotInterface
 */
interface SlotCollectionInterface extends \Countable, \IteratorAggregate, \ArrayAccess {
    /**
     * Get quantity of cards
     *
     * @return integer
     */
    public function countCards();

    /**
     * Get included packs
     *
     * @return \AppBundle\Entity\Pack[]
     */
    public function getIncludedPacks();

    /**
     * Get all slots sorted by type code
     *
     * @return array
     */
    public function getSlotsByType();

    /**
     * Get all slot counts sorted by type code
     *
     * @return array
     */
    public function getCountByType();

    /**
     * Get all slot counts sorted by sphere code
     *
     * @return array
     */
    public function getCountBySphere();


    /**
     * Get the hero deck
     *
     * @return \AppBundle\Model\SlotCollectionInterface
     */
    public function getHeroDeck();

    /**
     * Get the draw deck
     *
     * @return \AppBundle\Model\SlotCollectionInterface
     */
    public function getDrawDeck();

    /**
     * Get the content as an array card_code => qty
     *
     * @return array
     */
    public function getContent();

    /**
     * Get the starting threat
     *
     * @return int
     */
    public function getStartingThreat();
}

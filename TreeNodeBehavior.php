<?php

/**
 * This behavior adds tree node functionality to your CActiveRecord models.
 * 
 * @property-read integer $parentId
 * @property-read integer $sequence
 * @property-read integer|boolean $maxSequence
 * @property-read integer $newMaxSequence
 * 
 * @method CActiveRecord getOwner()
 */
class TreeNodeBehavior extends CActiveRecordBehavior
{
    /**
     * Owner parent id attribute name.
     * @var integer
     */
    public $parentIdAttribute = 'parentId';
    /**
     * Owner sequence attribute name.
     * @var integer
     */
    public $sequenceAttribute = 'sequence';
    
    # Gettrers #
    
    /**
     * Returns node parent id.
     * @param CActiveRecord $node oprional. Default node is current.
     * @return integer
     */
    public function getParentId($node = null)
    {
        if ($node === null)
            $node = $this->getOwner();
        
        return $node->getAttribute($this->parentIdAttribute);
    }
    
    /**
     * Returns node sequence.
     * @param CActiveRecord $node oprional. Default node is current.
     * @return integer
     */
    public function getSequence($node = null)
    {
        if ($node === null)
            $node = $this->getOwner();
        
        return $node->getAttribute($this->sequenceAttribute);
    }
    
    /**
     * Returns max sequence value for given parent id.
     * @param integer $parentId parent id. Default value is current node parent id
     * @return integer|boolean max sequence value or false if parent has no child nodes
     */
    public function getMaxSequence($parentId = null)
    {
        $owner = $this->getOwner();
        $db = $owner->getDbConnection();
        
        if ($parentId === null)
            $parentId = $this->getParentId();
        
        $parentIdAttribute = $db->quoteColumnName($this->parentIdAttribute);
        $sequenceAttribute = $db->quoteColumnName($this->sequenceAttribute);
        
        return $db->createCommand()
            ->select(array(new CDbExpression("MAX({$sequenceAttribute})")))
            ->from($owner->tableName())
            ->where($parentIdAttribute . ' = :pId')
            ->bindParam(':pId', $parentId)
            ->queryScalar();
    }
    
    /**
     * Returns sequence value that should be new last value for current parent
     * @param integer $parentId parent id. Default value is current node parent id
     * @return integer
     */
    public function getNewMaxSequence($parentId = null)
    {
        $sequence = $this->getMaxSequence($parentId);
        
        return $sequence !== false ? $sequence + 1 : 0;
    }
    
    /**
     * Returns node position to move node to
     * @param CActiveRecord $node
     * @return array
     */
    protected function getNodePosition($node)
    {
        $parentId = $this->getParentId($node);
        $sequence = $this->getSequence($node);
        
        $isLocalShift =
            $parentId == $this->getParentId() &&
            $sequence > $this->getSequence();
        
        if ($isLocalShift)
            --$sequence; // Because sequence will be decreased with remove() method
        
        return array($parentId, $sequence);
    }
    
    # Move methods #
    
    /**
     * Prepends node into another node.
     * This node will become first child of parent node.
     * @param CActiveRecord $parent
     */
    public function prependTo($parent)
    {
        $this->move($parent->getPrimaryKey(), 0);
    }
    
    /**
     * Appends node into another node.
     * This node will become last child of parent node.
     * @param CActiveRecord $parent
     */
    public function appendTo($parent)
    {
        $parentId = $parent->getPrimaryKey();
        $this->move($parentId, $this->getNewMaxSequence($parentId));
    }
    
    /**
     * Inserts node before another node.
     * @param CActiveRecord $parent
     */
    public function insertBefore($node)
    {
        list($parentId, $sequence) = $this->getNodePosition($node);
        
        $this->move($parentId, $sequence);
    }
    
    /**
     * Inserts node after another node.
     * @param CActiveRecord $parent
     */
    public function insertAfter($node)
    {
        list($parentId, $sequence) = $this->getNodePosition($node);
        
        $this->move($parentId, $sequence + 1);
    }
    
    /**
     * Removes node from current sequence.
     */
    public function remove()
    {
        $this->getOwner()->updateCounters(
            array($this->sequenceAttribute => -1),
            $this->createCriteria('>')
        );
    }
    
    # Events #
    
    /**
     * Inits sequence attribute.
     * @param CEvent $event
     * @return boolean
     */
    public function beforeSave($event)
    {
        if ($this->getOwner()->getIsNewRecord()) {
            $this->getOwner()->setAttribute(
                $this->sequenceAttribute,
                $this->getNewMaxSequence()
            );
        }
        
        return true;
    }
    
    /**
     * Removes node from current sequence.
     * @param CEvent $event
     * @return boolean
     */
    public function beforeDelete($event)
    {
        $this->remove();
        
        return true;
    }
    
    # Internal methods #
    
    /**
     * Increases following nodes sequence to make space for current node.
     */
    protected function shiftFollowing()
    {
        $this->getOwner()->updateCounters(
            array($this->sequenceAttribute => 1),
            $this->createCriteria('>=')
        );
    }
    
    /**
     * Decreases sequence for nodes between with sequence $start and $finish
     * @param integer $start
     * @param integer $finish
     */
    protected function unshiftBetween($start, $finish)
    {
        $criteria = new CDbCriteria();
        $criteria->compare($this->parentIdAttribute, $this->getParentId());
        $criteria->addBetweenCondition($this->sequenceAttribute, $start, $finish);
        
        $this->getOwner()->updateCounters(
            array($this->sequenceAttribute => -1),
            $criteria
        );
    }
    
    /**
     * Creates DB criteria to find current node neighbour nodes
     * @param string $operation sequence value comparison operator
     * It recognizes the following operators:
     * 
     * * <: the sequence must be less than current node sequence.
     * * >: the sequence must be greater than current node sequence.
     * * <=: the sequence must be less than or equal to current node sequence.
     * * >=: the sequence must be greater than or equal to current node sequence.
     * * <>: the sequence must not be the same as current node sequence.
     * * =: the sequence must be equal to current node sequence.
     * * none of the above: the sequence must be equal to current node sequence.
     * 
     * @return \CDbCriteria
     */
    protected function createCriteria($operation = '')
    {
        $criteria = new CDbCriteria();
        $criteria->compare(
            $this->parentIdAttribute,
            $this->getParentId()
        );
        $criteria->compare(
            $this->sequenceAttribute,
            $operation . $this->getSequence()
        );
        return $criteria;
    }
    
    /**
     * Sets parent id and sequence values for current node.
     * @param integer $parentId
     * @param integer $sequence
     */
    protected function setPosition($parentId, $sequence)
    {
        $owner = $this->getOwner();
        $owner->setAttribute($this->parentIdAttribute, $parentId);
        $owner->setAttribute($this->sequenceAttribute, $sequence);
    }
    
    /**
     * Moves current node to given position.
     * @param integer $parentId
     * @param integer $sequence
     */
    protected function move($parentId, $sequence)
    {
        Yii::trace("Moving to $parentId:$sequence", 'treenode');
        
        $this->remove();
            
        $this->setPosition($parentId, $sequence);
        
        $this->shiftFollowing();
        
        $this->save();
    }
    
    /**
     * Saves current node position.
     */
    protected function save()
    {
        $this->getOwner()->saveAttributes(array(
            $this->parentIdAttribute,
            $this->sequenceAttribute,
        ));
    }
}

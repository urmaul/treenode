<?php

/**
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
    public $parentIdAttribute = 'parentId';
    public $sequenceAttribute = 'sequence';
    
    # Gettrers #
    
    public function getParentId()
    {
        return $this->getOwner()->getAttribute($this->parentIdAttribute);
    }
    
    public function getSequence()
    {
        return $this->getOwner()->getAttribute($this->sequenceAttribute);
    }
    
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
    
    public function getNewMaxSequence($parentId = null)
    {
        $sequence = $this->getMaxSequence($parentId);
        
        return $sequence !== false ? $sequence + 1 : 0;
    }
    
    protected function getNodePosition($node)
    {
        $parentId = $node->getAttribute($this->parentIdAttribute);
        $sequence = $node->getAttribute($this->sequenceAttribute);
        
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
     * Inserts node after another node.
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
     * Detaches node from current sequence.
     */
    public function remove()
    {
        $this->getOwner()->updateCounters(
            array($this->sequenceAttribute => -1),
            $this->createCriteria('>')
        );
    }
    
    # Events #
    
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
    
    public function afterDelete($event)
    {
        $this->remove();
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
    
    protected function setPosition($parentId, $sequence)
    {
        $owner = $this->getOwner();
        $owner->setAttribute($this->parentIdAttribute, $parentId);
        $owner->setAttribute($this->sequenceAttribute, $sequence);
    }
    
    protected function move($parentId, $sequence)
    {
        Yii::trace("Moving to $parentId:$sequence", 'treenode');
        
        $this->remove();
            
        $this->setPosition($parentId, $sequence);
        
        $this->shiftFollowing();
        
        $this->save();
    }
    
    protected function save()
    {
        $this->getOwner()->saveAttributes(array(
            $this->parentIdAttribute,
            $this->sequenceAttribute,
        ));
    }
}

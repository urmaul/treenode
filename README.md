# Yii TreeNode Behavior

This behavior adds tree node functionality to your CActiveRecord models.

## Features

### Getters

* **getNewMaxSequence($parentId = null)** - returns sequence value that should be new last value for current parent,

### Move methods

* **prependTo($parent)** - prepends node into another node.
* **appendTo($parent)** - appends node into another node.
* **insertBefore($node)** - inserts node before another node.
* **insertAfter($node)** - inserts node after another node.
* **remove()** - removes node from current sequence.

### Handled events

* **beforeSave** - inits sequence attribute to make it last for current parent.
* **beforeDelete** - removes node from current sequence.

## How to attach

```php
public function behaviors()
{
    return array(
        'asTreeNode' => array(
            'class' => 'ext.behaviors.treenode.TreeNodeBehavior',
            'parentIdAttribute' => 'parentId',
            'sequenceAttribute' => 'sequence',
        ),
    );
}
```

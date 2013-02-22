# Yii TreeNode Behavior

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
# Визуальное дерево

## Установка

1) composer install
2) активировать плагин

## Код

### Методы

#### Создание дочерних или соседних
```
// Create a new node, which is the root (if parent is not specified). You can also specify the column parent, which will automatically maintain the tree structure., return new model
$child = Menu::create($attributes);
```

```
// Add an existing node to the child. The $child parameter can be a model instance/collection/id/array containing id, return bool
$menu->addChild($child);
$menu->addChild(12);
$menu->addChild('12');
$menu->addChild([3, 4, 5]);
```

```
// Move to the lower level of $parent, and all its lower level nodes will also move accordingly. The $parent parameter can be model instance/id, return bool
$menu->moveTo($parent);
$menu->moveTo(2);
$menu->moveTo('2');
```

```
// Add one or more sibling nodes, and all subordinate nodes of $siblings will also move accordingly. $siblings can be model instance/collection/id/array containing id, return bool
$menu->addSibling($siblings);
$menu->addSibling(2);
$menu->addSibling('2');
$menu->addSibling([2,3,4]);
```

```
// Create a new sibling node, return new model
$menu->createSibling($attributes);
```


#### Выборка групп элементов

```
// Get all descendants, return model collection
$menu->getDescendants();
```


```
// Get all descendants, including yourself, return model collection
$menu->getDescendantsAndSelf();
```


```
// Get all ancestors, return model collection
$menu->getAncestors();
``` 


```
// Get all ancestors, including yourself, return model collection
$menu->getAncestorsAndSelf();
```


```
// Get all children (direct subordinates), return model collection
$menu->getChildren();
```


```
// Get the superior node, return model
$menu->getParent();
```


```
// Get the root (the root node returns itself), return model
$menu->getRoot();
```


```
// Get all sibling nodes, return model collection
$menu->getSiblings();
```


```
// Get all sibling nodes including itself, return model collection
$menu->getSiblingsAndSelf();
```


```
// Get all orphan nodes (orphan nodes refer to records that are not maintained in the closureTable table)
Menu::getIsolated();
```


```
// Use range to query isolated nodes
Menu::isolated()->where('id', '>', 5)->get();
```


```
// Get all roots
Menu::getRoots();
```


```
// Scope - the same as above
Menu::onlyRoot()->get();
```
<blockquote>
A scope, the same as getRoots()  The above getXxx() methods all contain a query constructor, such as getDescendants() corresponding to queryDescendants(),

This allows you to add more conditions to the query such as: orderBy,

You can use it like this
```
$menu->queryDescendants()->where('id', '>', 5)->orderBy('sort','desc')->get();
```

Note that the four methods getRoot(), getParent(), getRoots(), and getIsolated() do not have query constructors.

If you want to get results that only contain a single or multiple fields, you can pass in parameters in the getXxx() method, such as:
```
$menu->getAncestors(['id','name']);
```
</blockquote>

#### Генерация древа

```
// Generate tree from current node, return tree
$menu->getTree();
```


```
// The current node is used as the root to generate a tree, sorted by the sort field, return tree
$menu->getTree(['sortColumn', 'desc']);
```


```
// Same as above, return tree
$menu->getDescendantsAndSelf()->toTree();
```


```
// Get the multi tree rooted at all children
$menu->getDescendants()->toTree();
```


```
// Generate a tree from the root node, return tree
$menu->getRoot()->getTree();
```
```
// Side tree, does not include itself and subordinates, return tree
$menu->getBesideTree();
```
```
// Your table may contain multiple trees. If you want to get them one by one, you can do this
$multiTree = Menu::all()->toTree();
```

#### Логические проверки

```
// Whether it's a root node
$menu->isRoot();
```

```
// Whether it is a leaf node
$menu->isLeaf();
```
```
// Whether to isolate the node
$menu->isIsolated();
``` 
```
// Is it the superior of $descendant?
$menu->isAncestorOf($descendant);
```
```
// Is it a subordinate of $ancestor?
$menu->isDescendantOf($ancestor);
```
```
// Is it a direct subordinate of $parent?
$menu->isChildOf($parent);
```
```
// Is it the direct superior of $child?
$menu->isParentOf($child);
```
```
// Whether it is the same level of $sibling (same superior level)
$menu->isSiblingOf($sibling);
```
```
// Return true if $beside is neither itself nor a descendant of itself
$menu->isBesideOf($beside);
```

#### Удаление

```
// Deleting (including soft deletion) a record will remove all associations with itself.
// And all its children will become the root (parent = 0), which means that all children will establish their own tree.
$menu->delete();

// Supports soft deletion. The restore() of soft deletion will restore to the corresponding location based on the records in its parent column. 
// You don't need to care about the records in the closure table, it has already been done for you.
```

#### Обслуживание
```
// Clean up redundant associated information
Menu::deleteRedundancies();
  
$menu = Menu::find(20);
  
// Repair the association of this node, which will re-establish the records in the `closure` table
$menu->perfectNode();
  
// Repair the tree association. Note: This operation will trace back to the root node and then traverse the entire tree from the root to call perfectNode(). 
// If your tree is very large, it will consume a lot of resources, so please use it with caution.
//The solution is to use a queue and use perfectNode() on each node
$menu->perfectTree();
```
# Tree builder helper        

this is a small library for symfony console to print a tree in a fluent and flexible way. 

The output is similar as the linux tree command, an can be used to debug/output relational data.

### Usage:

```
$tree = new TreeHelper($output);

$tree
    ->newNode('first')
        ->addValue('fist value')
        ->addValue('second value')
        ->newNode('fist sub')
            ->addValue('fist sub value')
            ->addValue('second sub value')
            ->newNode('fist sub sub')
                ->addValue('fist sub sub value')
                ->addValue('second sub sub value')            
            ->end()
        ->end()
    ->newNode('second')
        ->addValue('second value')
        ->addValue('second second value')
    ->end();
    
$tree->printTree();    
```

or

```
$tree = new TreeHelper($output);
$tree->addArray([
    'first' => [
        0 => 'fist value',
        1 => 'second value',
        'fist sub' => [
            0 => 'fist sub value',
            1 => 'second sub value',
            'fist sub sub' => [
                'fist sub sub value',
                'second sub sub value',
            ],
        ],
    ],
    'second' => [
        'second value',
        'second second value',
    ]
]);

```

that will output:

```
.
│
└── first
    ├── fist value
    ├── second value
    ├── fist sub
    │   ├── fist sub value
    │   ├── second sub value
    │   └── fist sub sub
    │       ├── fist sub sub value
    │       └── second sub sub value
    └── second
        ├── second value
        └── second second value

```

for other example see the the tests. 

### Methods

#### newNode($name<string>)
Create an new node and bind it to the parent
#### addNode($node<object:self>)
Append node to tree
#### end()
Will return the parent object (if set else null)
#### getRoot()
Will return the root node
#### printTree($output<object:OutputInterface>)
Print the tree to output
#### findNode($name<string>)
Search a node by title
#### toArray()
Returns a array representation of the data
#### getTitle()
Returns the title of the node
#### setTitle($title<string>)
Sets the title of the node
#### addValue($value)
Add a value to the node, this can be a valid scalar or a object with method __toString
#### addArray($array<array>)
Add a nested array to node
#### setValues($array<array>)
Add a flat array to node, this be set as the values of the node
#### setParent($parent<object:self>)
Set the parent of the node, will trow a RuntimeException is the the node all ready
is linked to the given node, so we don`t get infinite loops.
#### getNodes()
Get all the (child) nodes defined in the node, all the nodes are only saved in the root
so to get all nodes you probably have to do this $t->getRoot()->getNodes(); else it will
return the nodes where the parent is set as node where from you calling the method.
#### setMaxDepth($depth<int>)
Set the max depth of the node values, this can be done globally (on root) or on a specific node.
#### getMaxDepth()
Return the max depth that is set
#### setFormats()
Set the form that is used for example:
```
$tree->setFormats([
    TreeHelper::LINE_PREFIX_EMPTY => '  ',
    TreeHelper::LINE_PREFIX => '│ ',
    TreeHelper::TEXT_PREFIX => '├ ',
    TreeHelper::TEXT_PREFIX_END => '└ ',
]);
```
no it will use a 2 space indent instead of 4, see tests
#### setFormat($id<int>, $format<string>)
Overwrite a specific format



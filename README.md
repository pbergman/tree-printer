# Tree builder helper        

this is a small library for symfony console to print a tree in a fluent and flexible way. 

The output is similar as the linux tree command, an can be used to debug/output relational data.

## Usage:

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


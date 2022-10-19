<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */

namespace PBergman\Console\Tests;

use PBergman\Console\Helper\TreeHelper;
use Symfony\Component\Console\Output\StreamOutput;

class TreeHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test interface
     */
    public function testInstance()
    {
        $output = $this->getOutputStream();
        $teeHelper = new TreeHelper($output);
        $branch = $teeHelper->newNode('foo');
        $this->assertInstanceOf('PBergman\Console\Helper\TreeHelper', $branch);
        $this->assertNotNull($branch->end());
        $this->assertNull($teeHelper->end());
        $this->assertSame($teeHelper, $branch->end());
    }

    /**
     * @dataProvider testTreeRenderProvider
     */
    public function testTree($tree, $result)
    {
        $output = $this->getOutputStream();
        $tree()->printTree($output);
        $this->assertEquals($result, $this->getOutputContent($output));
    }

    /**
     * @expectedException              RuntimeException
     * @expectedExceptionMessage       Circular reference detected while setting child to parent
     */
    public function testCircularReference()
    {
        $tree = new TreeHelper();
        $tree->setParent($tree);
    }


    /**
     * @expectedException              RuntimeException
     * @expectedExceptionMessage       Circular reference detected while setting child to parent
     */
    public function testDeepCircularReference()
    {
        $tree = new TreeHelper();
        $tree
            ->newNode('a')
            ->newNode('b')
            ->newNode('c')
            ->newNode('d');

        $tree->findNode('d')[0]->setParent($tree->findNode('b')[0]);


    }


    public function testArrayInput()
    {
        $output = $this->getOutputStream();
        $tree = new TreeHelper();
        $array = [
            'no-symbols',
            'alphabet' => [
                'lowercase' => range('a','f'),
                'uppercase' => range('A','F'),
            ],
            'numbers' => range(0,5),
        ];
        $tree->addArray($array);
        $tree->printTree($output);

        $result = <<<EOF
.
│
├── no-symbols
├── alphabet
│   ├── lowercase
│   │   ├── a
│   │   ├── b
│   │   ├── c
│   │   ├── d
│   │   ├── e
│   │   └── f
│   └── uppercase
│       ├── A
│       ├── B
│       ├── C
│       ├── D
│       ├── E
│       └── F

└── numbers
    ├── 0
    ├── 1
    ├── 2
    ├── 3
    ├── 4
    └── 5


EOF;

        $this->assertEquals($result, $this->getOutputContent($output));
    }

    protected function getOutputStream()
    {
        return new StreamOutput(fopen('php://memory', 'r+'), StreamOutput::VERBOSITY_NORMAL, false);
    }


    protected function getOutputContent(StreamOutput $output)
    {
        rewind($output->getStream());
        return str_replace(PHP_EOL, "\n", stream_get_contents($output->getStream()));
    }


    public function testTreeRenderProvider()
    {
        return [
            [
                function() {
                    $foo = new TreeHelper();
                    $foo
                        ->setTitle('bla')
                        ->addValue('ddd')
                        ->addValue('ggg');

                    $bar = new TreeHelper();
                    $bar
                        ->setTitle('aaa')
                        ->addValue('aaa')
                        ->addValue('aaa');


                    $tree = new TreeHelper();
                    return $tree
                        ->newNode('first')
                            ->addValue('bar')
                            ->addValue('bar')
                            ->newNode('second')
                                ->addValue('foo')
                                ->addValue('foo')
                                ->newNode('third')
                                    ->addValue('foo')
                                    ->addValue('foo')
                                ->end()
                                ->addNode($foo)
                                    ->addNode($foo)->end()
                                    ->addNode($foo)
                                        ->addNode($foo)
                                        ->end()
                                    ->end()
                                ->end()
                                ->addNode($foo)->end()
                            ->end()
                            ->addNode($foo)->end()
                            ->addNode($foo)->end()
                       ->end()
                            ->addNode($bar)->end()
                        ->newNode('foo')
                            ->addValue('bar')
                            ->addValue('bar')
                        ->end();
                },
                <<<EOT
.
│
├── first
│   ├── bar
│   ├── bar
│   ├── second
│   │   ├── foo
│   │   ├── foo
│   │   ├── third
│   │   │   ├── foo
│   │   │   └── foo
│   │   ├── bla
│   │   │   ├── ddd
│   │   │   ├── ggg
│   │   │   ├── bla
│   │   │   │   ├── ddd
│   │   │   │   └── ggg
│   │   │   └── bla
│   │   │       ├── ddd
│   │   │       ├── ggg
│   │   │       └── bla
│   │   │           ├── ddd
│   │   │           └── ggg
│   │   └── bla
│   │       ├── ddd
│   │       └── ggg
│   ├── bla
│   │   ├── ddd
│   │   └── ggg
│   └── bla
│       ├── ddd
│       └── ggg
├── aaa
│   ├── aaa
│   └── aaa
└── foo
    ├── bar
    └── bar


EOT
            ],
            [
                function() {
                    $foo = new TreeHelper();
                    $foo
                        ->setTitle('bla')
                        ->addValue('ddd')
                        ->addValue('ggg');

                    $bar = new TreeHelper();
                    $bar
                        ->setTitle('aaa')
                        ->addValue('aaa')
                        ->addValue('aaa');



                    $tree = new TreeHelper();

                    $tree->setFormats([
                        TreeHelper::LINE_PREFIX_EMPTY => '  ',
                        TreeHelper::LINE_PREFIX => '│ ',
                        TreeHelper::TEXT_PREFIX => '├ ',
                        TreeHelper::TEXT_PREFIX_END => '└ ',
                    ]);

                    return $tree
                        ->newNode('first')
                            ->addValue('bar')
                            ->addValue('bar')
                            ->newNode('second')
                                ->addValue('foo')
                                ->addValue('foo')
                                ->newNode('third')
                                    ->addValue('foo')
                                    ->addValue('foo')
                                ->end()
                                ->addNode($foo)
                                    ->addNode($foo)->end()
                                    ->addNode($foo)
                                        ->addNode($foo)
                                        ->end()
                                    ->end()
                                ->end()
                            ->addNode($foo)->end()
                        ->end()
                            ->addNode($foo)->end()
                            ->addNode($foo)->end()
                        ->end()
                        ->addNode($bar)->end()
                        ->newNode('foo')
                            ->addValue('bar')
                            ->addValue('bar')
                        ->end();
                },
                <<<EOT
.
│
├ first
│ ├ bar
│ ├ bar
│ ├ second
│ │ ├ foo
│ │ ├ foo
│ │ ├ third
│ │ │ ├ foo
│ │ │ └ foo
│ │ ├ bla
│ │ │ ├ ddd
│ │ │ ├ ggg
│ │ │ ├ bla
│ │ │ │ ├ ddd
│ │ │ │ └ ggg
│ │ │ └ bla
│ │ │   ├ ddd
│ │ │   ├ ggg
│ │ │   └ bla
│ │ │     ├ ddd
│ │ │     └ ggg
│ │ └ bla
│ │   ├ ddd
│ │   └ ggg
│ ├ bla
│ │ ├ ddd
│ │ └ ggg
│ └ bla
│   ├ ddd
│   └ ggg
├ aaa
│ ├ aaa
│ └ aaa
└ foo
  ├ bar
  └ bar


EOT
            ],
            [
                function() {
                    $tree = new TreeHelper();
                    return $tree
                        ->newNode('foo')
                            ->addValue('first foo')
                            ->addValue('second foo')
                            ->newNode('sub foo')
                                ->addValue('1 sub foo')
                                ->addValue('2 sub foo')
                                ->addValue('3 sub foo')
                                ->addValue('4 sub foo')
                                ->addValue('5 sub foo')
                                ->addValue('6 sub foo')
                            ->end()
                            ->addValue('third foo')
                        ->end()
                        ->newNode('bar')
                            ->addValue('first bar')
                            ->addValue('second bar')
                            ->addValue('third bar')
                        ->end();
                },
                <<<EOT
.
│
├── foo
│   ├── first foo
│   ├── second foo
│   ├── sub foo
│   │   ├── 1 sub foo
│   │   ├── 2 sub foo
│   │   ├── 3 sub foo
│   │   ├── 4 sub foo
│   │   ├── 5 sub foo
│   │   └── 6 sub foo
│   └── third foo
└── bar
    ├── first bar
    ├── second bar
    └── third bar


EOT
            ],
            [
                function() {
                    $tree = new TreeHelper();
                    return $tree
                        ->newNode('foo title')
                            ->addValue('foo')
                            ->addValue('bar')
                            ->newNode('bar title')
                                ->addValue('bar')
                            ->end()
                        ->end();
                },
                <<<EOT
.
│
└── foo title
    ├── foo
    ├── bar
    └── bar title
        └── bar


EOT
            ],
            [
                function() {
                    $tree = new TreeHelper();
                    return $tree
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
                            ->end()
                        ->end();
                },
                <<<EOT
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


EOT
            ],
            [
                function() {
                    $tree = new TreeHelper();
                    return $tree
                        ->newNode('alphabet')
                            ->newNode('lowercase')
                                ->setValues(range('a','z'))
                            ->end()
                            ->newNode('uppercase')
                                ->setValues(range('A','Z'))
                            ->end()
                        ->end()
                        ->setMaxDepth(2);
                },
                <<<EOT
.
│
└── alphabet
    ├── lowercase
    └── uppercase


EOT
            ],
            [
                function() {
                    $tree = new TreeHelper();
                    return $tree
                        ->newNode('alphabet')
                            ->newNode('lowercase')
                                ->setValues(range('a','z'))
                            ->end()
                            ->newNode('uppercase')
                                ->setMaxDepth(2)
                                ->setValues(range('A','Z'))
                            ->end()
                        ->end()
                        ->findNode('lowercase')[0];
                },
                <<<EOT
.
│
└── lowercase
    ├── a
    ├── b
    ├── c
    ├── d
    ├── e
    ├── f
    ├── g
    ├── h
    ├── i
    ├── j
    ├── k
    ├── l
    ├── m
    ├── n
    ├── o
    ├── p
    ├── q
    ├── r
    ├── s
    ├── t
    ├── u
    ├── v
    ├── w
    ├── x
    ├── y
    └── z


EOT
            ],

        ];
    }

}

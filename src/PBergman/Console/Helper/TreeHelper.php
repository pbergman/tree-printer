<?php
/**
 * @author    Philip Bergman <pbergman@live.nl>
 * @copyright Philip Bergman
 */
namespace PBergman\Console\Helper;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TreeHelper
 *
 * a helper class to build a tree in a fluent way.
 *
 * the output is similar as the tree command in linux.
 *
 *
 * @package PBergman\Bundle\AdAlertBundle\Helper
 */
class TreeHelper
{
    /** @var \SplObjectStorage  */
    protected $nodes;
    /** @var  self|null */
    protected $parent;
    /** @var  string */
    protected $title;
    /** @var  array */
    protected $data;
    /** @var  OutputInterface */
    protected $output;
    /** @var  int */
    protected $maxDepth;

    const LINE_PREFIX_EMPTY = 1;
    const LINE_PREFIX = 2;
    const TEXT_PREFIX = 3;
    const TEXT_PREFIX_END = 4;
    const MAX_DEPTH_MARKER_VALUE = '**##MAX_DEPTH##**';

    /** @var array  */
    protected $formats = [
        self::LINE_PREFIX_EMPTY => '    ',
        self::LINE_PREFIX => '│   ',
        self::TEXT_PREFIX => '├── ',
        self::TEXT_PREFIX_END => '└── ',
    ];

    function __construct()
    {
        $this->nodes = new \SplObjectStorage();
    }

    /**
     * @param   $name
     * @return  $this
     */
    public function newNode($name)
    {
        $node = new self();
        $node->setTitle($name);
        return $this->addNode($node);
    }

    /**
     * @param   TreeHelper $node
     * @return  $this
     */
    public function addNode(self $node)
    {
        if (null === $node->getTitle()) {
            throw new \InvalidArgumentException('Given node does not have a title!');
        }
        // to fix if node is added multiple times
        if ($this->getRoot()->getNodes()->contains($node)) {
            $newNode = new self();
            $newNode
                ->setTitle($node->getTitle())
                ->setValues($node->getValues());
            $node = $newNode;
        }
        if (null === $node->end()) {
            $node->setParent($this);
        }
        if (is_null($this->parent)) {
            $this->nodes->attach($node);
        } else {
            $root = $this->getRoot();
            $root->addNode($node);
        }
        return $node;
    }

    /**
     * Print tree to output stream
     *
     * @param OutputInterface $output
     */
    public function printTree(OutputInterface $output)
    {
        $this->output = $output;
        $array = $this->toArray();
        $this->write(!(empty($this->title)) ? $this->title : '.');
        $this->write('│');
        if (!empty($this->data)) {
            $this->writeData($this->data, count($array) > 0);
        }
        foreach ($array as $index => $firstChild) {
            $haveChildren =  !empty($firstChild['children']);
            if (self::isLast($array, $index)) {
                $titlePrefix = $this->formats[self::TEXT_PREFIX_END];
                $dataPrefix = $this->formats[self::LINE_PREFIX_EMPTY];
            } else {
                $titlePrefix = $this->formats[self::TEXT_PREFIX];
                $dataPrefix = $this->formats[self::LINE_PREFIX];
            }
            $this->write(sprintf('%s%s', $titlePrefix, $firstChild['title']));
            $this->writeData($firstChild['data'], $haveChildren, $dataPrefix);
            $this->writeChildren($firstChild['children'], $dataPrefix);
        }
        $this->write('');
    }

    /**
     * @param array     $data
     * @param bool      $hasChildren
     * @param string    $prefix
     */
    protected function writeData($data, $hasChildren, $prefix = null)
    {
        $depth = 0;
        if (!empty($data)) {
            foreach ($data as $index => $line) {
                $depth++;
                if (false === $hasChildren && self::isLast($data, $index)) {
                    if ($line === self::MAX_DEPTH_MARKER_VALUE) {
                        $this->write(sprintf('%s¦', $prefix));
                    } else {
                        $this->write(sprintf('%s%s%s', $prefix, $this->formats[self::TEXT_PREFIX_END], $line));
                    }
                } else {
                    if (!is_null($this->maxDepth) && $this->maxDepth <= $depth) {
                        $this->write(sprintf('%s¦', $prefix));
                        break;
                    } else {
                        $this->write(sprintf('%s%s%s', $prefix, $this->formats[self::TEXT_PREFIX], $line));
                    }
                }
            }
        }
    }

    /**
     * @param array     $children
     * @param string    $prefix
     */
    protected function writeChildren($children, $prefix = null)
    {
        foreach ($children as $index => $child) {
            $hasChildren = !empty($child['children']);
            if (self::isLast($children, $index)) {
                $textPrefix =  $this->formats[self::TEXT_PREFIX_END];
                $newPrefix = $prefix . $this->formats[self::LINE_PREFIX_EMPTY];
            } else {
                $textPrefix =  $this->formats[self::TEXT_PREFIX];
                $newPrefix = $prefix . $this->formats[self::LINE_PREFIX];
            }
            $this->write(sprintf('%s%s%s', $prefix, $textPrefix, $child['title']));
            $this->writeData($child['data'], $hasChildren, $newPrefix);
            if ($hasChildren) {
                $this->writeChildren($child['children'], $newPrefix);
            }
        }
    }

    /**
     * @param   string  $name
     * @return  array|null|self[]
     */
    public function findNode($name)
    {
        if (is_null($this->parent)) {
            $return = null;
            $this->nodes->rewind();
            while ($this->nodes->valid()) {
                /** @var self $node */
                $node = $this->nodes->current();
                if ($node->getTitle() === $name) {
                    $return[] = $node;
                }
                $this->nodes->next();
            }
            return $return;
        } else {
            return $this->getRoot()->findNode($name);
        }
    }

    /**
     * get all objects and print them to array
     * this can be done on a child node and than
     * it will only get the nodes under that node
     *
     * @return array
     */
    public function toArray()
    {
        $return = [];
        if (null !== $children = $this->getNodesFromParent($this)) {
            /** @var self $child */
            foreach ($children as $child) {
                $values = $child->getValues();
                if (null !== $maxDepth = $child->getMaxDepth()) {
                    if ($maxDepth < count($values)) {
                        $values = array_slice($values, 0, $maxDepth);
                        $values[] = self::MAX_DEPTH_MARKER_VALUE;
                    }
                }
                $return[] = [
                    'title'     => $child->getTitle(),
                    'data'      => $values,
                    'children'  => $child->toArray(),
                ];
            }
        }
        return $return;
    }

    /**
     * Will return all nodes that have the given parent
     *
     * @param   TreeHelper $parent
     * @return  array|null
     */
    protected  function getNodesFromParent(self $parent)
    {
        $nodes = $this->getRoot()->getNodes();
        $nodes->rewind();
        $return = null;
        while ($nodes->valid()) {
            /** @var self $node */
            $node = $nodes->current();
            if ($node->end() === $parent) {
                $return[] = $node;
            }
            $nodes->next();
        }
        return $return;
    }

    /**
     * will trace back node to root and returns the stack
     *
     * @param   TreeHelper $node
     * @return  array
     */
    protected function getTrace(self $node)
    {
        $return[] = $node;
        $instance = $node;
        while (null !== $end = $instance->end()) {
            $return[] = $end;
            $instance = $end;
        }

        return $return;
    }

    /**
     * @return $this
     */
    public function end()
    {
        return $this->parent;
    }

    /**
     * Will try to get the root node based on set parent
     *
     * @return TreeHelper
     */
    public function getRoot()
    {
        $root = $this;
        while (null !== $end = $root->end()) {
            $root = $end;
        }
        return $root;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param   string  $title
     * @return  $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param   mixed  $value
     * @return  $this
     */
    public function addValue($value)
    {
        if (is_scalar($value) || is_object($value) && method_exists($value, '__toString')) {
            $this->data[] = (string) $value;
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid value given of type "%s", expecting a scalar or method that implements __toString', gettype($value)));
        }
        return $this;
    }

    /**
     * Method to build a the node twit multidimensional arrays
     */
    public function addArray($stack)
    {
        foreach ($stack as $title => $values) {
            $node = new self();
            $node->setParent($this);
            $node->setTitle($title);
            foreach($values as $key => $value) {
                if (is_array($value)) {
                    $node->addArray([$key => $value]);
                } else {
                    $node->addValue($value);
                }
            }
            $this->addNode($node);
        }
    }

    /**
     * set a stack of array instead of one by one
     *
     * @param   array $values
     * @return  $this
     */
    public function setValues(array $values)
    {
        $this->data = [];
        foreach ($values as $value) {
            $this->addValue($value);
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->data;
    }

    /**
     * @param  mixed $parent
     * @return $this
     * @throws \RuntimeException
     */
    public function setParent(self $parent)
    {
        if (in_array($parent, $this->getTrace($this), true)) {
            throw new \RuntimeException('Circular reference detected while setting child to parent');
        }
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return \SplObjectStorage
     */
    public function getNodes()
    {
        if (is_null($this->parent)) {
            return $this->nodes;
        } else {
            return $this->getNodesFromParent($this);
        }

    }

    /**
     * helper function to get last key of array this can be
     * used to determine if we are last in a foreach loop
     *
     * @param   array $data
     * @return  mixed
     */
    public static function lastKey(array $data)
    {
        $keys = array_keys($data);
        return end($keys);
    }

    /**
     * check if current key is last in stack
     *
     * @param   array $data
     * @param   $key
     * @return  bool
     */
    public static function isLast(array $data, $key)
    {
        return  self::lastKey($data) === $key;
    }

    /**
     * will print message if output is set
     *
     * @param $message
     */
    protected  function write($message)
    {
        if (!is_null($this->output)) {
            $this->output->writeln($message);
        }
    }

    /**
     * overwrite internal styling
     *
     * @param  array $formats
     */
    public function setFormats(array $formats)
    {
        $this->formats = $formats;
    }

    /**
     * overwrite internal style element
     *
     * @param int       $id
     * @param string    $format
     */
    public function setFormat($id, $format)
    {
        $this->formats[$id] = $format;
    }


    /**
     * @param int $maxDepth
     * @return $this
     */
    public function setMaxDepth($maxDepth)
    {
        $this->maxDepth = $maxDepth;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxDepth()
    {
        return $this->maxDepth;
    }

}
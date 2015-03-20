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

    const LINE_PREFIX_EMPTY = 1;
    const LINE_PREFIX = 2;
    const TEXT_PREFIX = 3;
    const TEXT_PREFIX_END = 4;

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

        foreach ($array as $index => $firstChild) {

            $haveChildren =  !empty($firstChild['children']);

            if (self::isLast($array, $index)) {
                $this->write(sprintf('%s%s', $this->formats[self::TEXT_PREFIX_END], $firstChild['title']));
                $this->writeData($firstChild['data'], $haveChildren , $this->formats[self::LINE_PREFIX_EMPTY]);
                $this->writeChildren($firstChild['children'], $this->formats[self::LINE_PREFIX_EMPTY]);
            } else {
                $this->write(sprintf('%s%s', $this->formats[self::TEXT_PREFIX], $firstChild['title']));
                $this->writeData($firstChild['data'], $haveChildren, $this->formats[self::LINE_PREFIX]);
                $this->writeChildren($firstChild['children'], $this->formats[self::LINE_PREFIX]);
            }
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
        if (!empty($data)) {
            foreach ($data as $index => $line) {
                if (false === $hasChildren && self::isLast($data, $index)) {
                    $this->write(sprintf('%s%s%s', $prefix, $this->formats[self::TEXT_PREFIX_END], $line));
                } else {
                    $this->write(sprintf('%s%s%s', $prefix, $this->formats[self::TEXT_PREFIX], $line));
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
     * @return  array|null
     */
    public function getNode($name)
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
            return $this->getRoot()->getNode($name);
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
                $return[] = [
                    'title'     => $child->getTitle(),
                    'data'      => $child->getValues(),
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
     * set a stack of array instead of one by one
     *
     * @param   array $values
     * @return  $this
     */
    public function setValues(array $values)
    {
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
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return \SplObjectStorage
     */
    public function getNodes()
    {
        return $this->nodes;
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
    public function write($message)
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
    public function setFormats($formats)
    {
        $this->formats = $formats;
    }

    /**
     * overwrite internal style element
     *
     * @param  array $formats
     */
    public function setFormat($id, $format)
    {
        $this->formats[$id] = $format;
    }

}
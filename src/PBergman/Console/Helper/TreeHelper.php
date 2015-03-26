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
    protected $data = [];
    /** @var  OutputInterface */
    protected static $output;
    /** @var  string */
    protected static $linePrefixEmpty = '    ';
    /** @var  string */
    protected static $linePrefix = '│   ';
    /** @var  string */
    protected static $textPrefix = '├── ';
    /** @var  string */
    protected static $textPrefixEnd = '└── ';
    protected static $nodeHashTemplate = "HASH_REF##[%s]";
    /** @var  int */
    protected $maxDepth;
    const LINE_PREFIX_EMPTY = 1;
    const LINE_PREFIX = 2;
    const TEXT_PREFIX = 3;
    const TEXT_PREFIX_END = 4;

    /** @var array  */
    protected $formats = [];

    function __construct()
    {
        $this->nodes = new \SplObjectStorage();
        $this->formats = [
            self::LINE_PREFIX_EMPTY => self::$linePrefixEmpty,
            self::LINE_PREFIX => self::$linePrefix,
            self::TEXT_PREFIX => self::$textPrefix,
            self::TEXT_PREFIX_END => self::$textPrefixEnd,
        ];
    }

    protected function createNodeReference(self $node)
    {
        return sprintf(self::$nodeHashTemplate, spl_object_hash($node));
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
        $node = clone $node;
        $node->setParent($this);
        $this->nodes->attach($node);
        $this->data[] = sprintf(self::$nodeHashTemplate, $this->nodes->getHash($node));
        return $node;
    }

    /**
     * Static function to print direct a array
     *
     * @param array $data
     * @param OutputInterface $output
     * @param array $formats
     */
    public static function Format(array $data, OutputInterface $output, array $formats = [], $maxDepth = null)
    {
        $format = array_replace([
            self::LINE_PREFIX_EMPTY => self::$linePrefixEmpty,
            self::LINE_PREFIX => self::$linePrefix,
            self::TEXT_PREFIX => self::$textPrefix,
            self::TEXT_PREFIX_END => self::$textPrefixEnd,
        ], $formats);

        self::$output = $output;
        self::write('.');
        self::write('│');
        self::render($data, '', $format, $maxDepth);
        self::write('');
    }

    /**
     * internal function to print the tree recursive
     *
     * @param array $data
     * @param string $prefix
     * @param array $format
     * @internal
     */
    protected static function render(array $data, $prefix = '', array $format, $maxDepth = null, $depth = 0)
    {
        if (!is_null($maxDepth) && $maxDepth <= $depth) {
            return;
        }
        foreach ($data as $index => $line) {
            if (self::isLast($data, $index)) {
                $titlePrefix = $prefix . $format[self::TEXT_PREFIX_END];
                $dataPrefix = $prefix . $format[self::LINE_PREFIX_EMPTY];
            } else {
                $titlePrefix = $prefix . $format[self::TEXT_PREFIX];
                $dataPrefix = $prefix . $format[self::LINE_PREFIX];;
            }
            if (preg_match('#^[0-9a-f]+@(?P<title>.+)$#i', $index, $m)) {
                $index = $m['title'];
            }
            switch (gettype($line)) {
                case 'boolean':
                case 'integer':
                case 'double':
                case 'string':
                case 'NULL':
                    self::write(sprintf('%s%s', $titlePrefix, $line));
                    break;
                case 'array':
                    self::write(sprintf('%s%s', $titlePrefix, $index));
                    self::render($line, $dataPrefix, $format, $maxDepth, ++$depth);
                    break;
                case 'object':
                    if (method_exists($line, '__toString')) {
                        self::write(sprintf('%s%s', $titlePrefix, (string) $line));
                    } else {
                        throw new \InvalidArgumentException(sprintf('Given object should implement a __toString for class %s', get_class($line)));
                    }
                    break;
                case 'resource':
                    if (get_resource_type($line) === 'stream') {
                        rewind($line);
                        self::write(sprintf('%s%s', $titlePrefix, stream_get_contents($line)));
                    } else {
                        throw new \InvalidArgumentException(sprintf('Only supporting streams as resource, given: %s', get_resource_type($line)));
                    }
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported type: %s', gettype($line)));
            }
        }
    }

    /**
     * Print tree to output stream
     *
     * @param OutputInterface $output
     */
    public function printTree(OutputInterface $output)
    {
        self::Format($this->toArray(), $output, $this->formats, $this->maxDepth);
    }


    /**
     * @param   string  $name
     * @return  array|null|self[]
     */
    public function findNode($name)
    {
        $result = [];
        if ($this->title === $name) {
            $result[] = $name;
        }
        return array_merge($result, $this->search($this->nodes, $name));
    }

    /**
     * Helper to search recersive
     *
     * @param \SplObjectStorage $nodes
     * @param                   $name
     *
     * @return array
     */
    protected function search(\SplObjectStorage $nodes, $name)
    {
        $result = [];
        $nodes->rewind();
        while ($nodes->valid()) {
            /** @var self $node */
            $node = $nodes->current();
            if($node->getTitle() === $name) {
                $result[] = $nodes->current();
            }
            $result = array_merge($result, $this->search($node->getNodes(), $name));
            $nodes->next();
        }
        return $result;
    }

    /**
     * get all objects and print them to array
     * this can be done on a child node and than
     * it will only get the nodes under that node
     *
     * @return  array
     */
    public function toArray()
    {
        $pattern = sprintf("/^%s$/i", sprintf(preg_quote(self::$nodeHashTemplate, '/'),'(?P<hash>[0-9a-f]+)'));
        $return = [];

        if (!func_get_args() && !empty($this->title)) {
            $id = sprintf('%s@%s', spl_object_hash($this), $this->getTitle());
            $returnRef = &$return[$id];
        } else {
            $returnRef = &$return;
        }

        foreach ($this->data as $data) {
            if (preg_match($pattern, $data, $m)) {
                $node = $this->getNodeByHash($m['hash']);
                $key = sprintf('%s@%s', $m['hash'], $node->getTitle());
                $returnRef[$key] = $node->toArray(false);
            } else {
                $returnRef[] = $data;
            }
        }
        return $return;
    }

    /**
     * get a node from given hash if not exists will return null
     *
     * @param   $hash
     * @return  null|self
     */
    protected function getNodeByHash($hash)
    {
        $this->nodes->rewind();
        while ($this->nodes->valid()) {
            $node = $this->nodes->current();
            if ($hash === $this->nodes->getHash($node)) {
                $this->nodes->detach($node);
                return $node;
            }
            $this->nodes->next();
        }
        return null;
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
     *
     * @param array $stack
     */
    public function addArray(array $stack, self $parent = null)
    {

        if (is_null($parent)) {
            $parent = $this;
        }

        foreach ($stack as $title => $values) {
            $node = new self();
            $node->setTitle($title);
            $node->setParent($parent);
            $parent->getNodes()->attach($node);
            $parent->addValue(sprintf(self::$nodeHashTemplate, spl_object_hash($node)));
            foreach($values as $key => $value) {
                if (is_array($value)) {
                    $node->addArray([$key => $value], $node);
                } else {
                    $node->addValue($value);
                }
            }
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
        $this->nodes->rewind();
        while ($this->nodes->valid()) {
            $this->data[] = sprintf(self::$nodeHashTemplate, $this->nodes->getHash($this->nodes->current()));
            $this->nodes->next();
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
    protected static function write($message)
    {
        if (!is_null(self::$output)) {
            self::$output->writeln($message);
        }
    }

    /**
     * overwrite internal styling
     *
     * @param  array $formats
     */
    public function setFormats(array $formats)
    {
        $this->formats = array_replace($this->formats, $formats);
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
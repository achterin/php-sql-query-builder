<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 6/3/14
 * Time: 12:07 AM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Sql\QueryBuilder\Syntax;

use NilPortugues\Sql\QueryBuilder\Manipulation\QueryException;
use NilPortugues\Sql\QueryBuilder\Manipulation\QueryFactory;
use NilPortugues\Sql\QueryBuilder\Manipulation\QueryInterface;
use NilPortugues\Sql\QueryBuilder\Manipulation\Select;

/**
 * Class Where.
 */
class Where
{
    const OPERATOR_GREATER_THAN_OR_EQUAL = '>=';
    const OPERATOR_GREATER_THAN = '>';
    const OPERATOR_LESS_THAN_OR_EQUAL = '<=';
    const OPERATOR_LESS_THAN = '<';
    const OPERATOR_LIKE = 'LIKE';
    const OPERATOR_NOT_LIKE = 'NOT LIKE';
    const OPERATOR_EQUAL = '=';
    const OPERATOR_NOT_EQUAL = '<>';
    const CONJUNCTION_AND = 'AND';
    const CONJUNCTION_AND_NOT = 'AND NOT';
    const CONJUNCTION_OR = 'OR';
    const CONJUNCTION_OR_NOT = 'OR NOT';
    const CONJUNCTION_EXISTS = 'EXISTS';
    const CONJUNCTION_NOT_EXISTS = 'NOT EXISTS';

    const WILDCARD_NONE = 0;
    const WILDCARD_FRONT = 1;
    const WILDCARD_BACK = 2;
    const WILDCARD_BOTH = 3;

    /**
     * @var array
     */
    protected $comparisons = [];

    /**
     * @var array
     */
    protected $betweens = [];

    /**
     * @var array
     */
    protected $isNull = [];

    /**
     * @var array
     */
    protected $isNotNull = [];

    /**
     * @var array
     */
    protected $booleans = [];

    /**
     * @var array
     */
    protected $match = [];

    /**
     * @var array
     */
    protected $ins = [];

    /**
     * @var array
     */
    protected $notIns = [];

    /**
     * @var array
     */
    protected $insSelect = [];

    /**
     * @var array
     */
    protected $notInsSelect = [];

    /**
     * @var array
     */
    protected $subWheres = [];

    /**
     * @var string
     */
    protected $conjunction = self::CONJUNCTION_AND;

    /**
     * @var QueryInterface
     */
    protected $query;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var array
     */
    protected $exists = [];

    /**
     * @var array
     */
    protected $notExists = [];

    /**
     * @var array
     */
    protected $notBetweens = [];

    /**
     * @param QueryInterface $query
     */
    public function __construct(QueryInterface $query)
    {
        $this->query = $query;
    }

    /**
     * Deep copy for nested references.
     *
     * @return mixed
     */
    public function __clone()
    {
        return \unserialize(\serialize($this));
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        $empty = \array_merge(
            $this->comparisons,
            $this->booleans,
            $this->betweens,
            $this->isNotNull,
            $this->isNull,
            $this->ins,
            $this->notIns,
            $this->subWheres,
            $this->exists
        );

        return 0 == \count($empty);
    }

    /**
     * @return string
     */
    public function getConjunction()
    {
        return $this->conjunction;
    }

    /**
     * @param string $operator
     *
     * @return $this
     *
     * @throws QueryException
     */
    public function conjunction($operator)
    {
        if (false === \in_array(
                $operator,
                [self::CONJUNCTION_AND, self::CONJUNCTION_OR, self::CONJUNCTION_OR_NOT, self::CONJUNCTION_AND_NOT]
            )
        ) {
            throw new QueryException(
                "Invalid conjunction specified, must be one of AND or OR, but '".$operator."' was found."
            );
        }
        $this->conjunction = $operator;

        return $this;
    }

    /**
     * @return array
     */
    public function getSubWheres()
    {
        return $this->subWheres;
    }

    /**
     * @param $operator
     *
     * @return Where
     */
    public function subWhere($operator = 'OR')
    {
        /** @var Where $filter */
        $filter = QueryFactory::createWhere($this->query);
        $filter->conjunction($operator);
        $filter->setTable($this->getTable());

        $this->subWheres[] = $filter;

        return $filter;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->query->getTable();
    }

    /**
     * Used for subWhere query building.
     *
     * @param Table $table string
     *
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * equals alias.
     *
     * @param      $column
     * @param int  $value
     * @param bool $isAlias
     *
     * @return static
     */
    public function eq($column, $value, $isAlias = false)
    {
        return $this->equals($column, $value, $isAlias);
    }

    /**
     * @param      $column
     * @param      $value
     * @param bool $isAlias
     *
     * @return static
     */
    public function equals($column, $value, $isAlias = false)
    {
        return $this->compare($column, $value, self::OPERATOR_EQUAL, $isAlias);
    }

    /**
     * @param        $column
     * @param        $value
     * @param string $operator
     * @param bool   $isAlias
     *
     * @return $this
     */
    protected function compare($column, $value, $operator, $isAlias)
    {
        $column = $this->prepareColumn($column, $isAlias);

        $this->comparisons[] = [
            'subject' => $column,
            'conjunction' => $operator,
            'target' => $value,
        ];

        return $this;
    }

    /**
     * @param      $column
     * @param bool $isAlias
     *
     * @return Column|Select
     */
    protected function prepareColumn($column, $isAlias)
    {
        //This condition handles the "Select as a a column" special case.
        //or when compare column is customized.
        if ($column instanceof Select || $column instanceof Column) {
            return $column;
        }

        $newColumn = [$column];

        return SyntaxFactory::createColumn($newColumn, $isAlias ? null : $this->getTable());
    }

    /**
     * @param string $column
     * @param int    $value
     * @param bool   $isAlias
     *
     * @return static
     */
    public function notEquals($column, $value, $isAlias = false)
    {
        return $this->compare($column, $value, self::OPERATOR_NOT_EQUAL, $isAlias);
    }

    /**
     * @param string $column
     * @param int    $value
     * @param bool   $isAlias
     *
     * @return static
     */
    public function greaterThan($column, $value, $isAlias = false)
    {
        return $this->compare($column, $value, self::OPERATOR_GREATER_THAN, $isAlias);
    }

    /**
     * @param string $column
     * @param int    $value
     * @param bool   $isAlias
     *
     * @return static
     */
    public function greaterThanOrEqual($column, $value, $isAlias = false)
    {
        return $this->compare($column, $value, self::OPERATOR_GREATER_THAN_OR_EQUAL, $isAlias);
    }

    /**
     * @param string $column
     * @param int    $value
     * @param bool   $isAlias
     *
     * @return static
     */
    public function lessThan($column, $value, $isAlias = false)
    {
        return $this->compare($column, $value, self::OPERATOR_LESS_THAN, $isAlias);
    }

    /**
     * @param string $column
     * @param int    $value
     * @param bool   $isAlias
     *
     * @return static
     */
    public function lessThanOrEqual($column, $value, $isAlias = false)
    {
        return $this->compare($column, $value, self::OPERATOR_LESS_THAN_OR_EQUAL, $isAlias);
    }

    /**
     * @param string $column
     * @param        $value
     * @param bool   $isAlias
     * @param int    $wildcardType
     *
     * @return static
     */
    public function like($column, $value, $isAlias = false, $wildcardType = self::WILDCARD_NONE)
    {
        return $this->compare($column, $this->wildcardValue($value, $wildcardType), self::OPERATOR_LIKE, $isAlias);
    }

    /**
     * @param string $column
     * @param        $value
     * @param bool   $isAlias
     * @param int    $wildcardType
     *
     * @return static
     */
    public function notLike($column, $value, $isAlias = false, $wildcardType = self::WILDCARD_NONE)
    {
        return $this->compare($column, $this->wildcardValue($value, $wildcardType), self::OPERATOR_NOT_LIKE, $isAlias);
    }

    /**
     * @param string[] $columns
     * @param mixed[]  $values
     *
     * @return static
     */
    public function match(array $columns, array $values)
    {
        return $this->genericMatch($columns, $values, 'natural');
    }

    /**
     * @param string[] $columns
     * @param mixed[]  $values
     * @param string   $mode
     *
     * @return $this
     */
    protected function genericMatch(array &$columns, array &$values, $mode)
    {
        $this->match[] = [
            'columns' => $columns,
            'values' => $values,
            'mode' => $mode,
        ];

        return $this;
    }

    /**
     * @param string $literal
     *
     * @return $this
     */
    public function asLiteral($literal)
    {
        $this->comparisons[] = $literal;

        return $this;
    }

    /**
     * @param string[] $columns
     * @param mixed[]  $values
     *
     * @return $this
     */
    public function matchBoolean(array $columns, array $values)
    {
        return $this->genericMatch($columns, $values, 'boolean');
    }

    /**
     * @param string[] $columns
     * @param mixed[]  $values
     *
     * @return $this
     */
    public function matchWithQueryExpansion(array $columns, array $values)
    {
        return $this->genericMatch($columns, $values, 'query_expansion');
    }

    /**
     * @param string $column
     * @param int[]  $values
     *
     * @return $this
     */
    public function in($column, array $values)
    {
        $this->ins[$column] = $values;

        return $this;
    }

    /**
     * @param string $column
     * @param int[]  $values
     *
     * @return $this
     */
    public function notIn($column, array $values)
    {
        $this->notIns[$column] = $values;

        return $this;
    }

    /**
     * @param string $column
     * @param Select $select
     *
     * @return $this
     */
    public function inSelect($column, Select $select)
    {
        $this->insSelect[$column] = $select;

        return $this;
    }

    /**
     * @param string $column
     * @param Select $select
     *
     * @return $this
     */
    public function notInSelect($column, Select $select)
    {
        $this->notInsSelect[$column] = $select;

        return $this;
    }

    /**
     * @param string $column
     * @param int    $a
     * @param int    $b
     * @param bool   $isAlias
     *
     * @return $this
     */
    public function between($column, $a, $b, $isAlias = false)
    {
        $column = $this->prepareColumn($column, $isAlias);
        $this->betweens[] = ['subject' => $column, 'a' => $a, 'b' => $b];

        return $this;
    }

    /**
     * @param string $column
     * @param int    $a
     * @param int    $b
     * @param bool   $isAlias
     *
     * @return $this
     */
    public function notBetween($column, $a, $b, $isAlias = false)
    {
        $column = $this->prepareColumn($column, $isAlias);
        $this->notBetweens[] = ['subject' => $column, 'a' => $a, 'b' => $b];

        return $this;
    }

    /**
     * @param string $column
     * @param bool   $isAlias
     *
     * @return static
     */
    public function isNull($column, $isAlias = false)
    {
        $column = $this->prepareColumn($column, $isAlias);
        $this->isNull[] = ['subject' => $column];

        return $this;
    }

    /**
     * @param string $column
     * @param bool   $isAlias
     *
     * @return $this
     */
    public function isNotNull($column, $isAlias = false)
    {
        $column = $this->prepareColumn($column, $isAlias);
        $this->isNotNull[] = ['subject' => $column];

        return $this;
    }

    /**
     * @param string $column
     * @param int    $value
     * @param bool   $isAlias
     *
     * @return $this
     */
    public function addBitClause($column, $value, $isAlias = false)
    {
        $column = $this->prepareColumn($column, $isAlias);
        $this->booleans[] = ['subject' => $column, 'value' => $value];

        return $this;
    }

    /**
     * @param Select $select
     *
     * @return $this
     */
    public function exists(Select $select)
    {
        $this->exists[] = $select;

        return $this;
    }

    /**
     * @return array
     */
    public function getExists()
    {
        return $this->exists;
    }

    /**
     * @param Select $select
     *
     * @return $this
     */
    public function notExists(Select $select)
    {
        $this->notExists[] = $select;

        return $this;
    }

    /**
     * @return array
     */
    public function getNotExists()
    {
        return $this->notExists;
    }

    /**
     * @return array
     */
    public function getMatches()
    {
        return $this->match;
    }

    /**
     * @return array
     */
    public function getIns()
    {
        return $this->ins;
    }

    /**
     * @return array
     */
    public function getNotIns()
    {
        return $this->notIns;
    }

    /**
     * @return array
     */
    public function getInsSelect()
    {
        return $this->insSelect;
    }

    /**
     * @return array
     */
    public function getNotInsSelect()
    {
        return $this->notInsSelect;
    }

    /**
     * @return array
     */
    public function getBetweens()
    {
        return $this->betweens;
    }

    /**
     * @return array
     */
    public function getNotBetweens()
    {
        return $this->notBetweens;
    }

    /**
     * @return array
     */
    public function getBooleans()
    {
        return $this->booleans;
    }

    /**
     * @return array
     */
    public function getComparisons()
    {
        return $this->comparisons;
    }

    /**
     * @return array
     */
    public function getNotNull()
    {
        return $this->isNotNull;
    }

    /**
     * @return array
     */
    public function getNull()
    {
        return $this->isNull;
    }

    /**
     * @param string $item
     * @param int    $wildcardType
     *
     * @return string
     */
    public function wildcardValue($item, $wildcardType)
    {
        $final = $item;

        switch ($wildcardType) {
            case self::WILDCARD_FRONT :
                $final = '%' . $item;
                break;

            case self::WILDCARD_BACK :
                $final = $item . '%';
                break;

            case self::WILDCARD_BOTH :
                $final = '%' . $item . '%';
                break;
        }

        return $final;
    }

    /**
     * @return QueryInterface
     */
    public function getQuery() {
        return $this->query;
    }
}

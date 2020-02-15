<?php
namespace Expreva;

/**
 * Defines a grammar of rules for the language. It supports:
 *
 * - Number
 * - Number prefix `+` and `-`
 * - Symbol for variables
 * - String wrapped in double- or single-quotes, and escape characters
 * - Arithmetic operations: add, subtract, multply, divide
 * - Assignment with `=`
 * - Group expression with `(` and `)`
 * - Statement separator `;`
 * - Anonymous function with arguments: `x =>` and `(x, y) =>`
 * - Function application with `arg->f`
 *
 * The order of rules below determines the order of regular expression match.
 */

return [
  [
    'match' => '/^\s*((\d+)?\.?\d+)\s*/',
    'name' => 'number',
    'power' => 0,
    'prefix' => function($parser) {
      return (float) $this->value;
    }
  ],
  [
    'match' => '/^\s*([a-zA-Z0-9_]+)\s*/',
    'name' => 'symbol',
    'power' => 0,
    'prefix' => function($parser) {
      return $this->value;
    },
  ],
  [
    /**
     * Match quoted strings with escaped characters
     *
     * @see https://stackoverflow.com/questions/249791/regex-for-quoted-string-with-escaping-quotes#answer-10786066
     *
     * Original reg exp is: /"([^"\\]*(\\.[^"\\]*)*)"|\'([^\'\\]*(\\.[^\'\\]*)*)\'/
     * For preg_match, converted: \\ -> \\\
     */
    'match' => '/^\s*"([^"\\\]*(\\\.[^"\\\]*)*)"|\'([^\'\\\]*(\\\.[^\'\\\]*)*)\'/',
    'name' => 'string',
    'power' => 0,
    'prefix' => function($parser) {
      // Quick unescape
      return ["`", json_decode("\"$this->value\"")];
    },
  ],
  [
    'match' => '/^\s*(\))\s*/',
    'name' => 'close expression',
    'power' => 0,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {},
  ],
  [
    'match' => '/^\s*(;)\s*/',
    'name' => 'end statement',
    'power' => 10,
    'infix' => function($parser, $left) {

      $right = $parser->next_expression($this->power);
      if (empty($left) && empty($right)) return;

      if (is_array($left) && $left[0] === 'do') {
        $result = $left;
        $result[] = $right;
      } else {
        $result = ['do', $left, $right];
      }

      return $result;
    },
  ],
  [
    'match' => '/^\s*(=>)\s*/',
    'name' => 'lambda',
    'power' => 70,
    'prefix' => function($parser) {
    },
    'infix' => function($parser, $left) {

      if (!$parser->is_argument_list($left)) {
        $left = ['args..', $left];
      }

      $right = $parser->next_expression(0);
      return ['lambda', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(->)\s*/', // Must come before `>`
    'name' => '->',
    'prefix' => function($parser) {},
    'power' => 60, // Weaker than `=>`
    'infix' => function($parser, $left) {
      $right = $parser->next_expression(0);
      return [$right, $left];
    },
  ],

  [
    'match' => '/^\s*(==)\s*/', // Must come before `=`
    'name' => '==',
    'power' => 30,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['==', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(\!=)\s*/',
    'name' => '!=',
    'power' => 30,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['!=', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(<=)\s*/',
    'name' => '<=',
    'power' => 30,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['<=', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(<)\s*/',
    'name' => '<',
    'power' => 30,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['<', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(>=)\s*/',
    'name' => '>=',
    'power' => 30,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['>=', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(>)\s*/',
    'name' => '>',
    'power' => 30,
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['>', $left, $right];
    },
  ],

  [
    'match' => '/^\s*(=)\s*/',
    'name' => 'set',
    'power' => 20,
    'prefix' => function() {},
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['set', $left, $right];
    },
  ],

  [
    'match' => '/^\s*([+])\s*/',
    'name' => '+',
    'power' => 50,
    'prefix' => function($parser) {
      /**
       * Positive sign binds stronger than / or *
       */
      return $parser->next_expression(70);
    },
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['+', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(-)\s*/',
    'name' => '-',
    'power' => 50,
    'prefix' => function($parser) {
      /**
       * Negative sign binds stronger than / or *
       */
      return -$parser->next_expression(70);
    },
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['-', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(\*)\s*/',
    'name' => '*',
    'power' => 60,
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['*', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(\/)\s*/',
    'name' => '/',
    'power' => 60,
    'infix' => function($parser, $left) {
      $right = $parser->next_expression($this->power);
      return ['/', $left, $right];
    },
  ],
  [
    'match' => '/^\s*(\()\s*/',
    'name' => 'open expression',
    'power' => 80,
    'prefix' => function($parser) {
      $value = $parser->next_expression(0);
      $rparen = $parser->next_expression($this->power);
      return $value;
    },
    'infix' => function($parser, $left) {
      $right = $parser->next_expression(0);
      $rparen = $parser->next_expression($this->power);
      if (is_null($left)) return $right;
      return [$left, $right];
    },
  ],
  [
    'match' => '/^\s*(\,)\s*/',
    'name' => 'argument separator',
    'power' => 5, // Stronger than )
    'prefix' => function($parser) {},
    'infix' => function($parser, $left) {
      /**
       * Add right side to argument list
       */
      $right = $parser->next_expression($this->power);

      if (!$parser->is_argument_list($left)) {
        $left = ['args..', $left];
      }
      $left []= $right;
      return $left;
    }
  ],
];

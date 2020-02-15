<?php
namespace Expreva;

/**
 * Parse a given source string into a syntax tree of instructions.
 *
 * @see https://en.wikipedia.org/wiki/Parsing#Parser
 *
 * Input is first tokenized by the lexer, and then parsed according to prefix and infix
 * functions of the generated tokens.
 */
class Parser {

  public $lexer;
  public $tokens = [];
  public $cursor = 0;
  public $instructions = [];

  function __construct($lexer = null) {
    if ($lexer) $this->lexer = $lexer;
  }

  function current() {
    if (isset($this->tokens[ $this->cursor ])) {
      return $this->tokens[ $this->cursor ];
    }
  }

  function next() {
    $this->cursor++;
  }

  /**
   * Parse string and return the resulting abstract syntax tree.
   */
  function parse($input = '') {

    $lexer = $this->lexer;

    $this->tokens = $lexer->tokenize($input);
    $this->cursor = 0;
    $this->instructions = [];

    $this->expressions = [];

    do {

      $instrs = $this->next_expression();

      if (is_null($instrs)) $instrs = [];
      if (!is_array($instrs)) $instrs = [$instrs];
      if (empty($instrs)) continue;

      $this->expressions []= $instrs;

    } while ($this->current());

    $count = count($this->expressions);
    if ($count===1) {
      // Unwrap expression
      $this->instructions = $this->expressions[0];
    } else if ($count > 1) {
      // Evaluate multiple expressions
      $this->instructions = ['do', $this->expressions];
    }

    return $this->instructions;
  }

  /**
   * Parse and return an expression.
   *
   * The value of `$right_binding_power` determines how much the expression binds to
   * the right.
   *
   * The `prefix` and `infix` methods of tokens call this function to extract expressions on
   * left or right side.
   */
  function next_expression($right_binding_power = 0) {

    if (!($token = $this->current())) return;
    $this->next();

    $left = $token->prefix($this);
    $token = $this->current();

    while ($token && $right_binding_power < $token->power) {

      if (!($token = $this->current())) break;
      $this->next();

      $left = $token->infix($this, $left);

      $left = $this->expand_arguments($left);

      $token = $this->current();
    }

    return $left;
  }

  function pop_expression() {
    return array_pop($this->expressions);
  }

  function push_expression($expr) {
    $this->expressions []= $expr;
  }

  /**
   * Expand arguments list
   */
  function expand_arguments($expr) {

    if (!is_array($expr) || !isset($expr[1])
      || !$this->is_argument_list($expr[1])
    ) return $expr;

    if ($expr[0]==='lambda') {
      // Argument definition: (lambda, [x, y, z], body)
      array_shift($expr[1]);
      return $expr;
    }

    // Function call arguments: f(x, y, z)
    $args = array_pop($expr);
    array_shift($args);
    return array_merge($expr, $args);
  }

  function is_argument_list($expr) {
    return is_array($expr) && $expr[0]==='args..';
  }
}

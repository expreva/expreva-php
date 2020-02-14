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

  public $previous_arguments = [];

  function __construct($lexer = null) {
    if ($lexer) $this->lexer = $lexer;
  }

  public function current() {
    if (isset($this->tokens[ $this->cursor ])) {
      return $this->tokens[ $this->cursor ];
    }
  }

  public function next() {
    $this->cursor++;
  }

  /**
   * Parse string and return the resulting abstract syntax tree.
   */
  public function parse($input = '') {

    $lexer = $this->lexer;

    $this->tokens = $lexer->tokenize($input);
    $this->cursor = 0;
    $this->instructions = [];
    $this->previous_arguments = [];

    $count = 0;
    $expressions = [];

    do {

      $instrs = $this->expression();

      if (is_null($instrs)) $instrs = [];
      if (!is_array($instrs)) $instrs = [$instrs];

      // Handle arguments list

      $instrs = $this->handle_arguments($instrs);

      if (empty($instrs)) continue;

      $expressions []= $instrs;
      $count++;

    } while ($this->current());

    if ($count===1) {
      // Unwrap expression
      $this->instructions = $expressions[0];
    } else if ($count > 1) {
      // Evaluate multiple expressions
      $this->instructions = ['do', $expressions];
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
  public function expression($right_binding_power = 0) {

    if (!($token = $this->current())) return;
    $this->next();

    $left = $token->prefix($this);
    $token = $this->current();

    while ($token && $right_binding_power < $token->power) {

      if (!($token = $this->current())) break;
      $this->next();

      $left = $token->infix($this, $left);
      if ($this->has_arguments()) return $left;

      $token = $this->current();
    }

    return $left;
  }

  /**
   * Handle comma operator to push arguments to previous or parent expression.
   *
   * The comma operator pushes the token on its right to a queue of arguments. After the whole
   * expression is parsed, the arguments are added to it, or its parent expression, by the function
   * operator `=>`.
   */

  function push_argument($instr) {
    $this->previous_arguments []= $instr;
  }

  function has_arguments() {
    return isset($this->previous_arguments[0]);
  }

  function pop_arguments() {
    $args = $this->previous_arguments;
    $this->previous_arguments = [];
    return $args;
  }

  function handle_arguments($instrs) {

    if (!isset($this->previous_arguments[0])) return $instrs;

    $previous = array_pop($instrs);
    $has_previous_expression = is_array($previous)
      // Check if it's a list as single argument
      && (!isset($previous[0]) || $previous[0]!=='list')
    ;

    if ($has_previous_expression) {

      // Add to previous expression's argument

      while ($arg = array_shift($this->previous_arguments)) {
        $previous []= $arg;
      }
      $instrs []= $previous;
      return $instrs;
    }

    // Push to parent expression

    // If not calling a function, make a list
    if (empty($instrs)) {
      $instrs []= 'list';
    }

    // Push previous argument back, and the rest
    $instrs []= $previous;
    while ($arg = array_shift($this->previous_arguments)) {
      $instrs []= $arg;
    }

    return $instrs;
  }
}

<?php
namespace Expreva;

/**
 * A token defines a lexical unit in the parse tree, such as number or operator.
 *
 * The lexer uses rules to match tokens in the source string, and generate token instances.
 *
 * The parser then calls each token's prefix and infix functions, to generate a syntax tree
 * of instructions.
 *
 * Each token implements either `prefix`, `infix`, or both.
 *
 * The`prefix` method  is used for prefix operators, identifiers and statements, when the token
 * occurs in prefix position. In Pratt's paper, this is called `nud`, or  "null denotation".
 *
 * The `infix` method deals with tokens occurring in infix positions.  In Pratt's parlance, it's
 * `led` or "left denotation".
 *
 * The property `$power` ("left-binding power") determines how tightly the token binds to the left.
 * For example:
 *
 *   a OP1 b OP2 c
 *
 * is interpreted as
 *
 *   (a OP1 b) OP2 c     // high left-binding power
 * or
 *   a OP1 (b OP2 c)     // low left-binding
 *
 * Having `$power` of 0 means the token doesn't bind at all, e.g. statement separators.
 */

class Token {

  public $name = '(token)';   /* Name of the token type */
  public $value = '';          /* Value of the token */
  public $power = 0;           /* Left-binding power */
  public $prefix;
  public $infix;

  public function __construct($definition = []) {
    foreach ($definition as $key => $value) {
      $this->$key = ($value instanceof \Closure)
        ? $value->bindTo( $this, $this )
        : $value
      ;
    }
  }

  public function error($message) {

    // TODO: Let parser handle this

    error_log("$message at line ".($this->line ? $this->line : '?')." column ".($this->column ? $this->column : '?'));
  }

  /**
   * Prefix position
   */
  public function prefix(Parser &$parser) {
    if (!empty($this->prefix)) {
      return call_user_func($this->prefix, $parser);
    }

    // if ($this->name==='(end)') return null;

    return $this->error("Unhandled prefix: ".$this->name);
  }

  /**
   * Infix position
   */
  public function infix(Parser &$parser, $left) {
    if (!empty($this->infix)) {
      return call_user_func($this->infix, $parser, $left);
    }

    return $this->error("Unhandled infix: ".$this->name);
  }
}


/**
 * A token signalling the end of the input.
 */
class EndToken extends Token {
  public $name = '(end)';
  public $power = 0;
  public function prefix(Parser &$parser) {
  }
  public function infix(Parser &$parser, $left) {
  }
}

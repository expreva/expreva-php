<?php

require_once __DIR__.'/token.php';
require_once __DIR__.'/rule.php';
require_once __DIR__.'/lexer.php';
require_once __DIR__.'/parser.php';
require_once __DIR__.'/evaluate.php';
require_once __DIR__.'/compile.php';

function expreva($source = null) {

  static $expreva, $lexer;

  if (!$lexer) {
    $lexer = new Expreva\Lexer(
      require_once __DIR__.'/rules.php'
    );
  }

  if (!$expreva) {

    $expreva = new class extends Expreva\Parser {

      // parse($str)

      function evaluate($instrs = null) {
        try {
          return Expreva\evaluate($instrs
            ? (is_string($instrs) ? $this->parse($instrs) : $instrs)
            : $this->tokens
          );
        } catch (\Exception $error) {
          $data = $error instanceof Expreva\RuntimeError
            ? $error->get_data()
            : [ 'message' => $error->getMessage() ]
          ;
          print_r($data);
        }
      }

      function to_string($instrs) {
        return Expreva\to_string($instrs);
      }
    };

    $expreva->lexer = $lexer;
  }

  return $source ? $expreva->evaluate($source) : $expreva;
}

<?php

require_once __DIR__.'/token.php';
require_once __DIR__.'/rule.php';
require_once __DIR__.'/lexer.php';
require_once __DIR__.'/parser.php';
require_once __DIR__.'/evaluate.php';

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
          print_r($error instanceof \Expreva\RuntimeError
          ? $error->get_data()
          : [
            'message' => $error->getMessage()
          ]);
        }
      }

      function to_string($instrs, $inner = false) {

        if (!is_array($instrs)) {
          if ($inner) return $instrs;
          return;
        }

        return '('. join(' ', array_map(function ($e) {
            return $this->to_string($e, true);
          }, $instrs)) . ')';
      }
    };

    $expreva->lexer = $lexer;
  }

  return $source ? $expreva->evaluate($source) : $expreva;
}

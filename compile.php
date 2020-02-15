<?php
namespace Expreva;

function to_string($instrs, $inner = false) {

  if (!is_array($instrs)) {
    if ($inner) return $instrs;
    return;
  }

  return '('. join(' ', array_map(function ($e) {
      return to_string($e, true);
    }, $instrs)) . ')';
}

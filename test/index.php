<?php

$expreva = expreva();

require_once __DIR__.'/tap.php';
$t = new \TAP();

try {

  $t->info('symbolic expressions');
  $t->is($expreva->to_string($expreva->parse('1 + 2')),'(+ 1 2)', 'simple expression');
  $t->is($expreva->to_string($expreva->parse('1 + 2 * 3')),'(+ 1 (* 2 3))','precendence');
  $t->is($expreva->to_string($expreva->parse('a = 1')), '(set a 1)','assignment');
  $t->is($expreva->to_string($expreva->parse('1 + 2; 3 + 4')),
    '(do (+ 1 2) (+ 3 4))','do'
  );
  $t->is($expreva->to_string($expreva->parse('(1 + 2) * 3')),
    '(* (+ 1 2) 3)', 'parenthesized subexpressions'
  );

  $t->info('functions');
  $expr = 'f(a,b,c)';
  $t->is($expreva->to_string($expreva->parse($expr)), '(f a b c)', $expr);

  $expr = '1+f(n, m(1+2))';
  $t->is($expreva->to_string($expreva->parse($expr)), '(+ 1 (f n (m (+ 1 2))))', $expr);

  $expr = 'a=>a+b+c';
  $t->is($expreva->to_string($expreva->parse($expr)), '(fn (a) (+ (+ a b) c))', $expr);

  $expr = '(a,b)=>a+b+c';
  $t->is($expreva->to_string($expreva->parse($expr)), '(fn (a b) (+ (+ a b) c))', $expr);

  $expr = '(a,b)=>a+b+c';
  $t->is($expreva->to_string($expreva->parse($expr)), '(fn (a b) (+ (+ a b) c))', $expr);

  $expr = 'f=((a,b)=>a+b);f(2,4)';
  $t->is($expreva->to_string($expreva->parse($expr)), '(do (set f (fn (a b) (+ a b))) (f 2 4))', $expr);

  $expr = '((a,b)=>a+b)(2,4)';
  $t->is($expreva->to_string($expreva->parse($expr)), '((fn (a b) (+ a b)) 2 4)', $expr);

  $t->done();

} catch (\Exception $error) {
  print_r($error instanceof RuntimeError
  ? $error->get_data()
  : [
    'message' => $error->getMessage()
  ]);
}

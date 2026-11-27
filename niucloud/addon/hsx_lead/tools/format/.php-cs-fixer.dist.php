<?php
declare(strict_types=1);

/**
 * Layout-only PHP formatting for hsx_lead.
 *
 * Every rule here is whitespace/line-break only and keeps the AST identical
 * (verified per batch by ast-compare.php). Deliberately NOT used:
 *  - rule sets (@PSR12, @PER-CS, @Symfony): they include `elseif`, quote, array
 *    syntax and parenthesis rules that change the AST or literals;
 *  - any risky rule; any rule that adds/removes parentheses, casts, imports,
 *    strict_types, quotes or `else if` -> `elseif`.
 * Long boolean chains stay on one line: php-cs-fixer does not wrap expressions.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(dirname(__DIR__, 2))
    ->exclude(['tools/format/vendor', 'node_modules', 'package', 'resource', 'docs', 'sql'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setIndent('    ')
    ->setUsingCache(false)
    ->setFinder($finder)
    ->setRules([
        // The main readability win: `a();b();c();` becomes one statement per line.
        'no_multiple_statements_per_line' => true,
        'statement_indentation' => true,
        'indentation_type' => true,
        // Reposition existing braces only; inserting braces breaks layout-only source contracts.
        'braces_position' => true,
        'control_structure_braces' => false,
        'control_structure_continuation_position' => true,
        'no_unneeded_braces' => false,
        // Spaces around keywords, operators, commas, types.
        'single_space_around_construct' => true,
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'none'],
        'unary_operator_spaces' => true,
        'ternary_operator_spaces' => true,
        'cast_spaces' => ['space' => 'single'],
        'method_argument_space' => ['on_multiline' => 'ignore'],
        'spaces_inside_parentheses' => ['space' => 'none'],
        'type_declaration_spaces' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'function_declaration' => true,
        'whitespace_after_comma_in_array' => true,
        'no_spaces_around_offset' => true,
        'object_operator_without_whitespace' => true,
        // Blank lines and trailing whitespace.
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'no_extra_blank_lines' => true,
        'single_blank_line_at_eof' => true,
        // Explicitly off: these change the AST or literal text.
        'elseif' => false,
        'no_unneeded_control_parentheses' => false,
        'array_syntax' => false,
        'single_quote' => false,
        'no_alternative_syntax' => false,
    ]);

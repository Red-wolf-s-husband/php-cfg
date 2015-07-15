<?php

/*
 * This file is part of PHP-CFG, a Control flow graph implementation for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace PHPCfg\AstVisitor;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitor\NameResolver as NameResolverParent;

class NameResolver extends NameResolverParent {

    protected static $builtInTypes = [
        'self',
        'parent',
        'static',
        'int',
        'integer',
        'long',
        'float',
        'double',
        'real',
        'array',
        'object',
        'bool',
        'boolean',
        'null',
        'void',
        'false',
        'true',
        'static',
        'string',
        'mixed',
        'resource',
        'callable',
    ];
    
    public function enterNode(Node $node) {
        parent::enterNode($node);
        $comment = $node->getDocComment();
        if ($comment) {
            $regex = "(@(param|return|var|type)\s+(\S+))";
            $comment->setText(
                preg_replace_callback(
                    $regex,
                    function ($match) {
                        $type = $this->parseTypeDecl($match[2]);
                        return "@{$match[1]} {$type}";
                    },
                    $comment->getText()
                )
            );
        }
    }

    protected function parseTypeDecl($type) {
        if (strpos($type, '|') !== false) {
            return implode('|', array_map([$this, 'parseTypeDecl'], explode('|', $type)));
        }
        if (strpos($type, '&') !== false) {
            return implode('&', array_map([$this, 'parseTypeDecl'], explode('&', $type)));
        }
        if (substr($type, 0, 1) === '?') {
            return '?' . $this->parseTypeDecl(substr($type, 1));
        }
        if (substr($type, -2) === '[]') {
            return $this->parseTypeDecl(substr($type, 0, -2)) . '[]';
        }
        if (substr($type, 0, 1) === '$') {
            // Variables aren't types
            return $type;
        }
        if (substr($type, 0, 1) === '\\') {
            // fully qualified is always fully qualified, but unqualify it
            return substr($type, 1);
        }
        $regex = '(^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\\)*[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$)';
        if (!preg_match($regex, $type)) {
            throw new \RuntimeException("Unknown type declaration found: $type");
        }
        if (in_array(strtolower($type), self::$builtInTypes)) {
            return $type;
        }
        // Now, we need to resolve the type
        $resolved = $this->resolveClassName(new Name($type));
        return $resolved->toString();
    }

}

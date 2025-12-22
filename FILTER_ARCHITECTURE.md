# OData Filter Architecture: Binary Tree Implementation

## TL;DR

The OData filter system converts filter expressions like `name eq 'Test' and price gt 100` into a **binary tree** structure where:

- **Each node** is either a comparison (`BasicQueryNode`), a logical operator (`CompositeQueryNode`), a relationship filter (`LambdaQueryNode`), or a negation (`NotQueryNode`)
- **The parser** (`FilterParser`) tokenizes the filter string and builds the tree using recursive descent parsing with proper operator precedence
- **The executor** (`FilterExecutor`) traverses the tree depth-first to generate Laravel Query Builder calls that produce SQL

**Example:**
```
Filter: "name eq 'Test' and price gt 100"

Tree:              CompositeQueryNode (AND)
                   /                  \
     BasicQueryNode                  BasicQueryNode
     name eq 'Test'                  price gt 100

SQL: WHERE "table"."name" = 'Test' AND "table"."price" > '100'
```

**Key Benefits:**
- ✅ Natural representation of logical operations
- ✅ Correct operator precedence automatically encoded
- ✅ Easy to test, debug, and extend
- ✅ Separation of parsing logic from execution logic

## Overview

The OData filter system uses a **binary tree** data structure to parse and execute complex filter expressions. This approach provides a clean, hierarchical representation of logical operations and comparisons, making it easy to traverse, transform, and execute filter queries.

## Core Concepts

### The Binary Tree Structure

Every filter expression is parsed into a tree where:
- **Leaf nodes** represent simple comparisons (e.g., `name eq 'Test'`)
- **Branch nodes** represent logical operations combining two sub-expressions (`and`, `or`)
- Each node has a **left** and **right** child (or references itself for leaf nodes)

### Why a Binary Tree?

A binary tree structure is ideal for filter expressions because:
1. **Natural representation** of logical operators (`and`/`or` always combine two expressions)
2. **Easy traversal** for execution (depth-first traversal)
3. **Operator precedence** is naturally encoded in the tree structure
4. **Composable** - small trees can be combined into larger trees
5. **Immutable** - nodes can be reused and transformed without side effects

## Node Types

The filter system defines four types of nodes, all extending the abstract `QueryNode` class:

### 1. BasicQueryNode (Leaf Node)

Represents a simple comparison operation.

**Properties:**
- `field`: The property name (e.g., `name`, `price`)
- `operator`: The comparison operator (e.g., `eq`, `gt`, `contains`)
- `value`: The value to compare against
- `negated`: Whether the comparison is negated

**Example:**
```php
// Represents: name eq 'Test'
new BasicQueryNode('name', 'eq', 'Test')

// Represents: price gt 100
new BasicQueryNode('price', 'gt', '100')
```

### 2. CompositeQueryNode (Branch Node)

Represents a logical operation combining two expressions.

**Properties:**
- `left`: Left child node (any QueryNode type)
- `operator`: Logical operator (`and` or `or`)
- `right`: Right child node (any QueryNode type)
- `grouped`: Whether this node was explicitly grouped with parentheses

**Example:**
```php
// Represents: name eq 'Test' AND price gt 100
new CompositeQueryNode(
    new BasicQueryNode('name', 'eq', 'Test'),
    'and',
    new BasicQueryNode('price', 'gt', '100')
)
```

### 3. LambdaQueryNode (Special Leaf Node)

Represents collection navigation with `any` or `all` operators.

**Properties:**
- `relation`: The relationship name (e.g., `relatedModels`)
- `lambda`: The lambda operator (`any` or `all`)
- `condition`: A nested QueryNode tree for the lambda condition
- `parameter`: The lambda parameter alias (e.g., `r` in `r/name eq 'Test'`)

**Example:**
```php
// Represents: relatedModels/any(r: r/name eq 'Test')
new LambdaQueryNode(
    'relatedModels',
    'any',
    new BasicQueryNode('r/name', 'eq', 'Test'),
    'r'
)
```

### 4. NotQueryNode (Unary Node)

Represents logical negation.

**Properties:**
- `inner`: The node to negate

**Example:**
```php
// Represents: NOT(name eq 'Test')
new NotQueryNode(
    new BasicQueryNode('name', 'eq', 'Test')
)
```

## Parsing: String to Tree

The `FilterParser` class converts OData filter strings into binary trees using **recursive descent parsing**.

### Parsing Algorithm

The parser uses operator precedence to build the tree correctly:

1. **`parseOrExpression()`** - Highest level, handles `or` operators
2. **`parseAndExpression()`** - Handles `and` operators (higher precedence than `or`)
3. **`parsePrimary()`** - Handles parentheses, functions, and basic comparisons

This precedence ensures that `and` binds tighter than `or`, matching standard logical operator precedence.

### Example: Simple AND Expression

**Filter:** `name eq 'Test' and price gt 100`

**Parsing steps:**
1. Parse `name eq 'Test'` → BasicQueryNode (left)
2. Encounter `and` keyword
3. Parse `price gt 100` → BasicQueryNode (right)
4. Combine into CompositeQueryNode

**Resulting tree:**
```
        CompositeQueryNode
           operator: 'and'
           /              \
          /                \
    BasicQueryNode      BasicQueryNode
    field: 'name'       field: 'price'
    op: 'eq'            op: 'gt'
    value: 'Test'       value: '100'
```

### Example: Mixed AND/OR Expression

**Filter:** `(name eq 'A' or name eq 'B') and price gt 100`

**Resulting tree:**
```
                CompositeQueryNode
                 operator: 'and'
                 /              \
                /                \
         CompositeQueryNode    BasicQueryNode
          operator: 'or'       field: 'price'
          grouped: true        op: 'gt'
          /            \       value: '100'
         /              \
   BasicQueryNode   BasicQueryNode
   field: 'name'    field: 'name'
   op: 'eq'         op: 'eq'
   value: 'A'       value: 'B'
```

### Example: Complex Nested Expression

**Filter:** `((name eq 'A' or name eq 'B') and price gt 100) or status eq 'active'`

**Resulting tree:**
```
                        CompositeQueryNode
                         operator: 'or'
                         /              \
                        /                \
              CompositeQueryNode      BasicQueryNode
               operator: 'and'        field: 'status'
               grouped: true          op: 'eq'
               /            \         value: 'active'
              /              \
       CompositeQueryNode   BasicQueryNode
        operator: 'or'      field: 'price'
        grouped: true       op: 'gt'
        /          \        value: '100'
       /            \
  BasicQueryNode  BasicQueryNode
  field: 'name'   field: 'name'
  op: 'eq'        op: 'eq'
  value: 'A'      value: 'B'
```

### Example: Lambda Expression

**Filter:** `relatedModels/any(r: r/name eq 'Test' and r/cost gt 100)`

**Resulting tree:**
```
           LambdaQueryNode
           relation: 'relatedModels'
           lambda: 'any'
           parameter: 'r'
           |
           condition (nested tree):
           |
           CompositeQueryNode
            operator: 'and'
            /              \
           /                \
    BasicQueryNode      BasicQueryNode
    field: 'r/name'     field: 'r/cost'
    op: 'eq'            op: 'gt'
    value: 'Test'       value: '100'
```

## Execution: Tree to SQL

The `FilterExecutor` class traverses the binary tree and builds SQL query constraints using Laravel's Query Builder.

### Execution Strategy

The executor uses **depth-first traversal** with different strategies based on node type:

#### 1. Executing BasicQueryNode (Leaf)

Directly translates to a SQL WHERE clause:
```php
// name eq 'Test' → WHERE "table"."name" = 'Test'
$builder->where('name', '=', 'Test');
```

#### 2. Executing CompositeQueryNode (Branch)

Recursively executes left and right children with the appropriate boolean operator:

**For AND nodes:**
```php
// Executes both children with 'where'
$this->execute($left, 'where');
$this->execute($right, 'where');
```

**For OR nodes:**
```php
// Groups in a closure to maintain precedence
$builder->where(function ($q) {
    $exec->execute($left, 'where');
    $exec->execute($right, 'orWhere');
});
```

#### 3. Executing LambdaQueryNode

Translates to EXISTS subqueries:

**`any` → `EXISTS`:**
```php
// relatedModels/any(r: r/name eq 'Test')
// → WHERE EXISTS (SELECT * FROM related_models WHERE ...)
$builder->whereHas('relatedModels', function ($q) {
    $q->where('name', '=', 'Test');
});
```

**`all` → `NOT EXISTS` with negated condition:**
```php
// relatedModels/all(r: r/cost gt 100)
// → WHERE NOT EXISTS (SELECT * FROM related_models WHERE cost <= 100)
$builder->whereDoesntHave('relatedModels', function ($q) {
    $q->where('cost', '<=', '100'); // Negated condition
});
```

### Traversal Order

The tree is traversed **left-to-right, depth-first**:

```
Given tree:      1 (AND)
                /       \
               /         \
              2 (eq)      3 (OR)
                         /     \
                        /       \
                       4 (gt)   5 (lt)

Execution order: 2 → 4 → 5 → 3 → 1
```

This ensures that:
1. Leaf conditions are evaluated first
2. Logical operators are applied in the correct order
3. Parentheses grouping is respected

## Real-World Example

Let's walk through a complete example from filter string to SQL.

### Input Filter
```
(name eq 'Premium' or status eq 'active') and relatedModels/any(r: r/cost gt 100)
```

### Step 1: Parse to Tree

```
                CompositeQueryNode
                 operator: 'and'
                 /              \
                /                \
         CompositeQueryNode    LambdaQueryNode
          operator: 'or'       relation: 'relatedModels'
          grouped: true        lambda: 'any'
          /            \       parameter: 'r'
         /              \      |
   BasicQueryNode   BasicQueryNode   condition:
   field: 'name'    field: 'status'  |
   op: 'eq'         op: 'eq'         BasicQueryNode
   value:           value:           field: 'r/cost'
   'Premium'        'active'         op: 'gt'
                                     value: '100'
```

### Step 2: Execute Tree

**Traversal order:**
1. Execute left branch (OR node with grouping)
2. Execute right branch (Lambda node)
3. Combine with AND

**Generated SQL:**
```sql
SELECT * FROM "test_models"
WHERE (
    "test_models"."name" = 'Premium' 
    OR "test_models"."status" = 'active'
)
AND EXISTS (
    SELECT * FROM "related_models"
    WHERE "test_models"."id" = "related_models"."test_model_id"
    AND "related_models"."cost" > '100'
)
LIMIT 100 OFFSET 0
```

## Advanced Features

### 1. Parentheses and Grouping

Explicit parentheses are tracked via the `grouped` flag on `CompositeQueryNode`. This ensures that:
- `(A or B) and C` → Groups the OR before applying AND
- `A or (B and C)` → Groups the AND before applying OR

### 2. Operator Precedence

The parser enforces standard precedence:
1. **Parentheses** (highest)
2. **NOT** (negation)
3. **AND**
4. **OR** (lowest)

### 3. Tree Transformation

The `negateNode()` method demonstrates tree transformation using **De Morgan's Laws**:

```php
// NOT(A AND B) → NOT(A) OR NOT(B)
// NOT(A OR B) → NOT(A) AND NOT(B)
```

This allows complex negation without wrapping everything in `NOT()`.

### 4. Mixed Operator Grouping

The executor detects when mixing function operators (like `contains`) with comparison operators and automatically groups them to maintain correct SQL precedence.

## Benefits of This Architecture

1. **Separation of Concerns**
   - Parsing logic separate from execution logic
   - Easy to test each component independently

2. **Extensibility**
   - New node types can be added easily
   - Custom operators can be implemented by extending `BasicQueryNode`

3. **Debugging**
   - Tree structure can be visualized using `toString()` methods
   - Each node is immutable and inspectable

4. **Performance**
   - Tree is built once and can be reused
   - No need to re-parse the same filter multiple times

5. **Type Safety**
   - Strong typing ensures correct tree structure
   - PHP type system catches errors at runtime

## Diagram Legend

```
CompositeQueryNode: Branch node (logical operator)
├── left: Left child node
├── operator: 'and' or 'or'
└── right: Right child node

BasicQueryNode: Leaf node (comparison)
├── field: Property name
├── operator: Comparison operator
└── value: Comparison value

LambdaQueryNode: Relationship traversal node
├── relation: Relationship name
├── lambda: 'any' or 'all'
├── parameter: Lambda parameter alias
└── condition: Nested QueryNode tree

NotQueryNode: Negation node
└── inner: Node to negate
```

## Testing Strategy

The binary tree structure makes testing straightforward:

1. **Unit Tests** - Test individual node creation and properties
2. **Parser Tests** - Verify correct tree structure from filter strings
3. **Executor Tests** - Verify correct SQL generation from trees
4. **Integration Tests** - End-to-end filter to query execution

## Conclusion

The binary tree approach to OData filter parsing provides a robust, maintainable, and extensible foundation. By separating parsing from execution and using immutable node structures, the system achieves both correctness and clarity.

The tree structure naturally represents the hierarchical nature of logical expressions, making it easy to reason about complex filters and ensuring correct operator precedence without ambiguity.


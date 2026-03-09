# Project Manifest — Application Data Grids

> **Source of Truth** for AI agent sessions. Describes the structure, APIs, data flows, and conventions of the `mistralys/application-datagrids` PHP library.

## Sections

| Section | File | Description |
|---|---|---|
| Tech Stack & Patterns | [tech-stack.md](tech-stack.md) | Runtime, frameworks, dependencies, architectural patterns, build tools. |
| File Tree | [file-tree.md](file-tree.md) | Annotated directory structure of the project. |
| Public API Surface | [api-surface.md](api-surface.md) | Public constructors, properties, and method signatures for all classes and interfaces. |
| Key Data Flows | [data-flows.md](data-flows.md) | Main interaction paths through the system. |
| Constraints & Conventions | [constraints.md](constraints.md) | Established rules, conventions, and known issues. |

## Project Overview

PHP library that abstracts the rendering of HTML data tables (grids). Provides a fluent, chainable API for defining columns, rows, and actions, with a pluggable renderer system (ships with a plain HTML renderer and a Bootstrap 5 renderer).

**Status:** Work in progress — core functionality and examples are operational.

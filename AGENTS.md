# AGENTS.md

## Overview

This project uses Laravel (backend) and React + Vite (frontend).

Always prioritize readability, maintainability, and simplicity.

---

# Core Philosophy

Think like an experienced software engineer.

Never overengineer.

Prefer existing Laravel and React features over creating custom abstractions.

If a feature can be solved in one file, do not split it into five.

Write code that another developer can understand in six months.

---

# Before Writing Code

Always:

- Read related files first.
- Understand existing architecture.
- Reuse existing patterns.
- Keep changes minimal.
- Explain the plan before making large changes.

Never assume.

---

# Simplicity Rules

Prefer:

Laravel Validation

over

Manual validation

Prefer:

Storage::disk()

over

Custom upload helper

Prefer:

Route Model Binding

over

Manual model lookup

Prefer:

Eloquent

over

Raw SQL

Prefer:

Collection methods

over

Long foreach chains

Prefer:

Existing Components

over

Creating new ones

---

# Do NOT Create

Unless explicitly requested:

- Repository Pattern
- Service Layer
- DTO
- Interface
- Trait
- Helper
- Utility Class
- Singleton
- Custom Hook
- New Dependency

Keep architecture flat.

---

# Laravel

Follow Laravel conventions.

Use:

- Controllers
- Models
- FormRequest (if project already uses them)
- Resources (only if already used)
- Middleware
- Policies

Avoid reinventing Laravel features.

---

# Database

Always:

- Use migrations.
- Use foreign keys.
- Use timestamps.
- Use soft deletes only when required.

Never:

- Modify production migrations.
- Hardcode IDs.

---

# Validation

Always validate request input.

Prefer:

$request->validate()

or

FormRequest

Never trust frontend input.

---

# Authentication

Reuse existing authentication flow.

Never duplicate auth logic.

Never bypass middleware.

---

# Upload

Always use:

Storage

Never:

move_uploaded_file()

Store only file paths.

Never store binary data in database.

Delete old files when replacing uploads.

---

# API

Responses should be consistent.

Example:

{
    "success": true,
    "message": "Banner created successfully.",
    "data": {...}
}

Errors:

{
    "success": false,
    "message": "Validation failed."
}

---

# Eloquent

Prefer:

User::find()

User::create()

User::update()

Avoid unnecessary Query Builder.

Avoid raw SQL.

---

# Controllers

Controllers should remain small.

Business logic belongs only where necessary.

Avoid methods longer than ~60 lines.

Extract only when complexity justifies it.

---

# React

Use:

Functional Components

Arrow Functions

Hooks

Keep components focused.

---

# React Components

Prefer:

Composition

over inheritance.

Avoid giant components.

If component exceeds ~250 lines,
consider splitting.

---

# State

Prefer:

useState

useMemo

useCallback

Only introduce Context if multiple components need shared state.

Avoid unnecessary global state.

---

# Styling

Reuse existing styling.

Avoid inline styles.

Follow project conventions.

---

# Naming

Use meaningful names.

Good:

UserController

BannerController

Bad:

Controller2

DataHelper

TempClass

---

# Comments

Comment WHY.

Do not comment WHAT.

Bad:

// increment i

Good:

// Prevent duplicate submissions.

---

# Error Handling

Never ignore exceptions.

Return useful messages.

Log unexpected errors.

---

# Performance

Avoid:

N+1 Query

Duplicate API calls

Unnecessary renders

Use eager loading.

---

# Security

Always:

Validate input

Escape output

Protect routes

Use CSRF where applicable

Never trust user input.

---

# Dependencies

Before installing a package:

Ask:

Can Laravel already do this?

If yes,

Do not install anything.

---

# Refactoring

Refactor only when it improves:

- readability
- maintainability
- simplicity

Never refactor unrelated code.

---

# Git

Keep commits focused.

One feature.

One fix.

One commit.

---

# When Debugging

Find root cause.

Never guess.

Read stack trace.

Read logs.

Check related code.

---

# Decision Making

When multiple solutions exist:

Choose:

1. Simpler
2. More readable
3. Laravel standard
4. Less code
5. Easier maintenance

---

# Communication

Before changing many files:

Explain:

- What will change
- Why
- Risks

After finishing:

Summarize:

- Files changed
- Reason
- Possible improvements

---

# Coding Style

Write code for humans.

Avoid clever tricks.

Prefer clarity over cleverness.

Readable code wins.

---

# Final Rule

If unsure:

Choose the Laravel way.

If still unsure:

Choose the simplest solution.
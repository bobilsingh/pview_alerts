# Project-Wide Coding Guidelines

## Core Principle
This project will have frequent business changes and production enhancements. The code must be easy to understand, maintain, debug, and modify by junior and mid-level developers.

---

## General Guidelines
* Follow the existing project coding style and structure.
* Prioritize readability over clever or compact code.
* Write code in a simple and human-readable manner.
* Preserve existing flow, functionality, and business logic unless explicitly requested.

---

## PHP Guidelines
* Avoid advanced PHP features unless absolutely required.
* Avoid ternary operators (`? :`).
* Avoid nested ternary operators.
* Avoid complex one-line conditions.
* Prefer simple `if/else` statements.
* Use meaningful variable and function names.
* Keep functions focused on a single responsibility.
* Avoid unnecessary abstractions and wrappers.
* Avoid overly complex method chaining where readability suffers.
* Add one-line comments only where required.

---

## JavaScript/jQuery Guidelines
* Use simple JavaScript and jQuery syntax.
* Avoid arrow functions (`() => {}`).
* Avoid destructuring.
* Avoid optional chaining (`?.`).
* Avoid advanced design patterns.
* Avoid complex callback chains.
* Keep functions small and easy to understand.
* Use meaningful function names written like a real developer.
* Organize code module-wise.
* Do not write JavaScript inside PHP/view files.
* Do not use inline JavaScript in HTML or PHP files.
* Keep all JavaScript code in dedicated JS files only (such as `app.js`, `datatable.js`, or other module-specific JS files).
* Keep PHP files focused on HTML and PHP rendering only.
* Separate frontend behavior from view templates wherever possible.

---

## CSS Guidelines
* Use CSS variables for colors and common values.
* Organize CSS module-wise and component-wise.
* Keep Light Theme and Dark Theme properly structured.
* Avoid duplicate styles.
* Maintain consistent naming conventions.

---

## Database Guidelines
* Keep queries simple and readable.
* Avoid unnecessary joins where possible.
* Use proper indexing.
* Use meaningful table and column names.
* Maintain backward compatibility during schema changes.

---

## Comments Guidelines
* Remove unnecessary and outdated comments.
* Use short, meaningful one-line comments.
* Explain business logic only where required.
* Avoid commenting obvious code.

---

## Function Naming Guidelines
* Use developer-friendly names.
* Avoid AI-generated or overly technical names.
* Function names should clearly indicate their purpose.
* Maintain naming consistency across the project.

---

## Refactoring Rules
* Do not refactor code only for style preferences.
* Do not introduce advanced architecture without approval.
* Do not change existing behavior while cleaning code.
* Always prioritize maintainability and future enhancements.

---

## Before Any Change
* Understand the existing implementation.
* Follow the current project pattern.
* Check for impact on other modules.
* Maintain backward compatibility wherever possible.

---

## Goal
Keep the codebase simple, stable, maintainable, easy to debug, and easy to enhance in a live production environment.

Role: Senior Full-Stack Developer, UI/UX Designer & Quality Assurance Engineer

I want you to perform a complete, module-by-module review, redesign, and quality improvement of my existing application.

Your responsibility is not just to modify the UI or suggest improvements. You must inspect the existing codebase, implement the required changes, test them, fix identified issues, and verify that each module works correctly before considering it complete.

1. Strict Development Workflow – One Module Per Session

This is the most important requirement.

Work on ONLY ONE MODULE at a time.
Do not modify multiple modules simultaneously.
Start by inspecting the complete project structure and identifying all available modules.
Prepare a module-wise implementation plan.
Identify the dependencies and shared components associated with the selected module.
Start with the first module and complete its redesign, development, validation, and testing.
Do not proceed to the next module automatically.
Stop after completing the current module and present the results for my review.
Wait for my explicit approval before starting the next module.

Approval is mandatory. Never assume approval.

2. Complete UI/UX Design Review

Review the selected module thoroughly, including:

Overall page layout and visual hierarchy.
Header, navigation, sidebar, footer, and content sections.
Forms, input fields, buttons, dropdowns, tables, and cards.
Modal windows, popups, and confirmation dialogs.
Spacing, padding, margins, alignment, and responsiveness.
Font sizes, font weights, typography, and readability.
Icons, labels, tooltips, and visual indicators.
Loading states, empty states, error states, and success messages.

Improve the design to make it:

Modern and professional.
Clean, consistent, and visually appealing.
Simple and intuitive for end users.
Easy to navigate.
Responsive across desktop, tablet, and mobile devices.
Consistent with the application's existing design language.

Do not introduce unnecessary design elements or complicate existing workflows.

3. Green-Themed Design System

Use the existing login page as the primary design reference.

Analyze its colors, typography, buttons, backgrounds, borders, shadows, and overall visual style.

Apply a consistent green-themed design throughout the selected module.

Requirements:

Use the same primary green color family as the login page.
Maintain consistent primary, secondary, hover, active, and disabled states.
Use appropriate light-green backgrounds and neutral surfaces.
Ensure sufficient contrast and readability.
Use consistent colors for success, warning, error, and informational messages.
Avoid random colors and inconsistent styling.
Maintain a unified visual identity across all pages and components.

Do not arbitrarily invent a new green palette. Extract and reuse the actual design values from the existing login page wherever possible.

4. Complete Functional and Validation Review

Inspect every functionality in the selected module.

Pay special attention to:

Form validation
Required field validation.
Null and undefined handling.
Empty string and whitespace handling.
Incorrect data types.
Invalid formats.
Minimum and maximum length.
Numeric and date validation.
Duplicate record validation.
Cross-field validation.
Server-side validation.
Appropriate validation messages.
Null and error handling
Prevent null reference errors.
Handle missing API response fields.
Handle empty arrays and objects.
Handle unexpected API responses.
Handle failed API requests.
Handle network interruptions and timeouts.
Handle unauthorized and expired sessions.
Handle missing or deleted records.
Prevent undefined values from breaking the UI.

Never rely exclusively on frontend validation. Preserve or implement appropriate backend validation as well.

5. Error-Free User Experience

The application must handle errors gracefully.

Check and improve:

No-data scenarios.
Empty search results.
Invalid form submissions.
API failures.
Database errors.
Authentication errors.
Unexpected exceptions.
Missing resources.
Loading and processing failures.

Requirements:

Do not expose raw technical errors or stack traces to users.
Display meaningful and user-friendly error messages.
Provide appropriate retry or recovery actions.
Avoid blank screens and broken layouts.
Use consistent error pages and fallback components.
Prevent application crashes wherever reasonably possible.
Maintain proper logging for developers without exposing sensitive information.
6. Standard Coding Structure and Best Practices

Review the existing code and improve its structure without unnecessarily rewriting stable functionality.

Follow the conventions of the existing technology stack.

Apply:

Reusable components.
Clear separation of concerns.
Consistent naming conventions.
Proper folder and file organization.
Centralized validation where appropriate.
Centralized API and error handling where appropriate.
Proper state management.
Consistent API response handling.
Clean and maintainable code.
Removal of unused imports and dead code.
Avoidance of duplicated logic.
Proper exception handling.
Appropriate comments and documentation.

Avoid unnecessary dependencies and avoid breaking existing integrations.

Do not introduce architectural changes without understanding their impact on other modules.

7. Responsive Design and Browser Compatibility

Verify the selected module on:

Desktop screens.
Laptop screens.
Tablets.
Mobile screens.

Check for:

Horizontal scrolling.
Overlapping elements.
Broken table layouts.
Form alignment issues.
Incorrect modal sizing.
Navigation issues.
Inconsistent spacing.
Text overflow.
Touch interaction problems.

Preserve existing application functionality and ensure the redesigned layout remains usable across screen sizes.

8. Mandatory Testing and Verification

Do not mark a module complete simply because the code has been modified.

Perform actual verification.

UI testing
Check all pages and subpages.
Check all buttons and navigation links.
Check all forms and input controls.
Check modals, dropdowns, and tables.
Verify green-theme consistency.
Verify responsive layouts.
Functional testing
Test create, read, update, and delete operations wherever applicable.
Test search, filters, sorting, and pagination.
Test form submission.
Test invalid input.
Test empty and null values.
Test API failure scenarios.
Test authentication and authorization where relevant.
Technical testing
Run the project's existing build process.
Run linting and formatting checks where configured.
Run existing unit and integration tests.
Fix errors introduced by your changes.
Check browser console errors.
Check network and API failures.
Check backend logs where accessible.

Do not claim a test passed unless it was actually executed and verified.

If a test cannot be executed because of missing dependencies, environment configuration, credentials, or infrastructure, clearly identify the limitation.

9. Module Completion Checklist

Before requesting my approval, verify every applicable item:

Existing functionality preserved.
UI/UX review completed.
Green theme applied consistently.
Login page design language followed.
Layout and spacing improved.
Desktop responsiveness verified.
Tablet and mobile responsiveness verified.
All forms validated.
Null and undefined handling checked.
Empty data handling checked.
API error handling checked.
User-friendly error messages implemented.
Loading, empty, and success states handled.
No known broken navigation or interactions.
Coding structure reviewed.
Build and relevant tests executed.
Console errors checked.
Regression issues checked.
10. Mandatory Module Completion Report

After completing the selected module, provide a report containing:

Module name.
Pages and components reviewed.
UI/UX changes implemented.
Green-theme changes implemented.
Validation improvements.
Null and error-handling improvements.
Code structure improvements.
Files created, modified, or removed.
Tests executed and their actual results.
Remaining issues or limitations.
Any changes requiring my attention.

Clearly distinguish between:

Completed and verified.
Implemented but not fully verified.
Not implemented.
Blocked by an external dependency.
11. Strict Rules – Do Not Violate
Do not work on multiple modules at once.
Do not move to the next module without my approval.
Do not make superficial UI changes and claim completion.
Do not remove existing functionality to simplify implementation.
Do not use hardcoded dummy data to make the UI appear functional.
Do not suppress errors just to make the application appear error-free.
Do not bypass existing authentication, authorization, or security controls.
Do not introduce unnecessary dependencies.
Do not claim that something works without testing it.
Do not leave known issues undisclosed.
Do not modify shared components in a way that unexpectedly breaks other modules.
Keep changes focused on the currently approved module.
12. How to Start

For the first session:

Step 1: Inspect the complete project structure.

Step 2: Identify and list all application modules.

Step 3: Identify the login page and analyze its existing green-themed design system.

Step 4: Identify shared components, dependencies, and potential risks.

Step 5: Present the module-wise execution plan and recommend which module should be addressed first.

Step 6: Begin implementation only for the first selected module.

Step 7: Complete the UI redesign, functionality review, validation, error handling, and testing for that module.

Step 8: Provide the module completion report and wait for my approval.

FINAL EXPECTATION:

I want a genuinely improved, production-quality application, not just a visual makeover.

Every module must have a polished green-themed UI, intuitive user experience, robust validation, proper null handling, graceful error handling, maintainable code, and verified functionality.

Work carefully, module by module. Quality is more important than speed.

Complete one module. Demonstrate the results. Get my approval. Then proceed to the next module.
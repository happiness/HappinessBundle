# HappinessBundle Agent Guide

Use this guide when working on the **HappinessBundle** plugin in `var/plugins/HappinessBundle`.

---

## Overview & Scope

- **Plugin Name**: `HappinessBundle` (`happiness/happiness-bundle` or `KimaiPlugin\HappinessBundle`)
- **Description**: Lightweight customization bundle that enhances the standard Kimai timesheet view by visually grouping records by weekday with daily totals and status badges.
- **Primary Capabilities**:
  - Groups timesheet table records by weekday (Monday through Sunday) with clear section headings.
  - Computes and displays daily duration totals alongside day names and localized dates.
  - Visual distinctions and badges for "today" and "weekend" rows.
  - Custom Twig extension (`happiness_this_week_range`) for working with current week date ranges.
  - Complete feature parity with Kimai's core datatable (modal editing, batch multi-updates, sorting, pagination, recording indicators, daily rate summaries).
- **Scope Boundary**: All bundle templates, extensions, configuration, and tests must remain strictly isolated within `var/plugins/HappinessBundle/`. **Never modify Kimai core files** in `src/`, `templates/`, `config/`, or `migrations/`.

---

## Stack & Requirements

- **Kimai Version**: `>= 2.0.0` (Kimai 2.x)
- **PHP Version**: `8.2` - `8.4` (DDEV default: `8.4`, PHP 8.4 compatible)
- **Framework**: Symfony 6.4 LTS, Twig
- **Frontend**: Bootstrap 5 with Tabler UI framework

---

## Repository Map

```text
var/plugins/HappinessBundle/
├── AGENTS.md                          # This agent guide
├── composer.json                      # Plugin metadata and Kimai version requirements
├── HappinessBundle.php                # Bundle entry point (implements App\Plugin\PluginInterface)
├── DependencyInjection/
│   └── HappinessExtension.php        # Extension implementing PrependExtensionInterface for Twig paths
├── Resources/
│   ├── config/
│   │   └── services.yaml              # Service registration & autowiring
│   └── views/
│       └── timesheet/
│           └── index.html.twig        # Overridden core timesheet view with weekday grouping
└── Twig/
    └── HappinessTwigExtension.php     # Custom Twig functions (e.g. happiness_this_week_range)
```

---

## Architectural Rules & Key Decisions

1. **Non-Intrusive View Overrides**:
   - `HappinessExtension` implements `Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface` to prepend `var/plugins/HappinessBundle/Resources/views` to `twig.paths`.
   - The view `Resources/views/timesheet/index.html.twig` overrides `templates/timesheet/index.html.twig` cleanly with zero modifications to Kimai core files.
2. **Dynamic Day Grouping Algorithm**:
   - In `timesheet/index.html.twig`, the template iterates through `dataTable` and tracks the transition between distinct days using `entry.begin|date_short`.
   - On each day boundary, a full-width header row (`<tr class="table-light weekday-group-header">`) is rendered with `colspan="{{ sortedColumns|length }}"` so it dynamically adapts to custom or hidden column configurations.
   - Day totals are precomputed in a single pass (`dayTotals`) and rendered in the weekday header badge (`{{ dayTotals[day]|duration }}`).
3. **Core Datatable Compatibility**:
   - All interactive hooks (`data-href="{{ path(editRoute, {'id': entry.id}) }}"`, batch selection checkboxes, modal dialog triggers, daily duration & rate summaries) must be preserved.

---

## Validation & Quality Assurance

Always validate changes using containerized DDEV or host CLI commands:

```bash
# Verify plugin registration & discovery in Kimai
ddev exec bin/console kimai:plugins
# (or on host): bin/console kimai:plugins

# Clear & rebuild cache after template or DI changes
ddev exec bin/console cache:clear
# (or on host): bin/console cache:clear

# Run static analysis (PHPStan)
ddev exec ./phpstan.sh core
# (or on host): ./phpstan.sh core

# Run code style fixer (PHP-CS-Fixer)
ddev exec ./php-cs-fixer.sh core
# (or on host): ./php-cs-fixer.sh core
```

---

## Coding Conventions

- Add `declare(strict_types=1);` at the top of every PHP file.
- Use native PHP 8 typing and attributes.
- Use strict comparisons (`===`, `!==`).
- Follow established Bootstrap 5 and Tabler UI classes (`bg-azure-lt`, `bg-muted-lt`, `badge bg-blue`).
- Leverage core Kimai Twig filters (`|day_name`, `|date_short`, `|duration`, `is today`, `is weekend`).

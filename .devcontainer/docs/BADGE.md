# Badge Usage Guide

<!--
SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

## Overview

This guide explains how to add the "Uses cs-grafting" badge to your repository to indicate that it uses the codespaces-grafting scion.

## The Badge

![Uses cs-grafting](./../assets/graft-badge.svg)

The badge features:
- A simplified icon showing the blue book and green scion (WordPress and GitHub icons removed for clarity at small sizes)
- Consistent styling with other repository badges
- Clear "uses: cs-grafting" text
- Green accent color (#76ce32) matching the scion theme

## Adding the Badge to Your Repository

### Option 1: Direct SVG Link (Recommended)

Add this markdown to your README.md:

```markdown
[![Uses cs-grafting](https://raw.githubusercontent.com/evlist/codespaces-grafting/main/.devcontainer/assets/graft-badge.svg)](https://github.com/evlist/codespaces-grafting)
```

This displays as:

[![Uses cs-grafting](https://raw.githubusercontent.com/evlist/codespaces-grafting/main/.devcontainer/assets/graft-badge.svg)](https://github.com/evlist/codespaces-grafting)

### Option 2: Copy to Your Repository

1. Copy the badge SVG file to your repository:
   ```bash
   mkdir -p .devcontainer/assets
   curl -o .devcontainer/assets/graft-badge.svg \
     https://raw.githubusercontent.com/evlist/codespaces-grafting/main/.devcontainer/assets/graft-badge.svg
   ```

2. Add this markdown to your README.md:
   ```markdown
   [![Uses cs-grafting](.devcontainer/assets/graft-badge.svg)](https://github.com/evlist/codespaces-grafting)
   ```

### Option 3: Shields.io Alternative

If you prefer a purely text-based badge via shields.io:

```markdown
[![Uses cs-grafting](https://img.shields.io/badge/uses-cs--grafting-76ce32)](https://github.com/evlist/codespaces-grafting)
```

This displays as:

[![Uses cs-grafting](https://img.shields.io/badge/uses-cs--grafting-76ce32)](https://github.com/evlist/codespaces-grafting)

## Badge Placement

We recommend placing the badge:
- After the "Open in GitHub Codespaces" badge (if present)
- With other project metadata badges (version, license, build status, etc.)
- Near the top of your README for visibility

Example badge section:
```markdown
[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=YOUR-ORG/YOUR-REPO)
[![Uses cs-grafting](https://raw.githubusercontent.com/evlist/codespaces-grafting/main/.devcontainer/assets/graft-badge.svg)](https://github.com/evlist/codespaces-grafting)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
```

## Icon Files

Two icon variants are available:

1. **badge-icon.svg** - Simplified icon for the badge (64x64px)
   - Contains only the blue book and green scion
   - Optimized for small sizes
   - No WordPress or GitHub icons

2. **icon.svg** - Full logo (256x256px)
   - Contains book, scion, WordPress, and GitHub icons
   - Suitable for larger displays and documentation

## Design Rationale

The simplified badge icon focuses on the core metaphor:
- **Blue book** - Represents the stock (existing repository)
- **Green scion** - Represents the grafted development environment

The WordPress and GitHub icons were removed from the badge version because:
- They become illegible at badge size (20px height)
- The core metaphor (grafting) is more universal than any specific platform
- Cleaner design improves recognition and readability

## License

The badge and icons are dual-licensed under:
- GPL-3.0-or-later
- MIT

You are free to use, modify, and redistribute them under either license.

## Feedback

If you have suggestions for improving the badge or icons, please [open an issue](https://github.com/evlist/codespaces-grafting/issues) or submit a pull request.

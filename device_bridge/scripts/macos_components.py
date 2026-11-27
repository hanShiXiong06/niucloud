"""Disable relocation for framework bundles discovered in the package staging root."""

import plistlib
import sys
from pathlib import Path

path = Path(sys.argv[1])
components = plistlib.loads(path.read_bytes())
for component in components:
    component["BundleIsRelocatable"] = False
    component["BundleIsVersionChecked"] = False
    component["BundleOverwriteAction"] = "upgrade"
path.write_bytes(plistlib.dumps(components))

# Deprecated monster portraits

These 405 `.gif` files are the portraits the Monsters page used before the site
rendered its own.

They came from outside the project, are drawn at a different scale than the
client's sprites, and — the important part — asking an outside service for the
image is what made the equipment section wrong in the first place (DECISIONS
D062). The portraits are now generated from our own `Tibia.dat` and `Tibia.spr`
by `tools/gen_monster_images.py` in the root repository, which is also what
proved our client's DAT/SPR layout (D064).

The page no longer requests these files: `core.monsters_images_extension` is
`.png`. They are kept only as a reference for comparison — regenerating the
current set and diffing against them is a quick way to notice that the sprite
composition broke.

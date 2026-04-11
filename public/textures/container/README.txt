Container PBR textures — two sets by manufacture_date

1) Clean (metal_plate_4k) — used when manufacture_date < 1 year ago
   Path: metal_plate_4k/textures/
   Files: metal_plate_diff_4k.jpg, metal_plate_ao_4k.jpg, metal_plate_nor_gl_4k.exr,
          metal_plate_rough_4k.exr, metal_plate_metal_4k.exr

2) Rusty (rusty_metal_grid_4k) — used when manufacture_date >= 1 year ago or null
   Path: rusty_metal_grid_4k/textures/
   Files: rusty_metal_grid_diff_4k.jpg, rusty_metal_grid_ao_4k.jpg,
          rusty_metal_grid_nor_gl_4k.exr, rusty_metal_grid_rough_4k.exr
   (no metalness map; metalness set to 0.5)

Container type (standard, high_cube, refrigerated, flat_rack) tints the texture
via CONTAINER_COLORS in useContainer3DScene.js.

import {
    Scene,
    PerspectiveCamera,
    WebGLRenderer,
    BoxGeometry,
    SphereGeometry,
    CylinderGeometry,
    PlaneGeometry,
    CircleGeometry,
    MeshStandardMaterial,
    Mesh,
    Group,
    Points,
    PointsMaterial,
    BufferGeometry,
    Float32BufferAttribute,
    AmbientLight,
    DirectionalLight,
    PointLight,
    Color,
    Vector2,
    Vector3,
    Raycaster,
    MathUtils,
    PMREMGenerator,
    TextureLoader,
    EquirectangularReflectionMapping,
} from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { Sky } from 'three/addons/objects/Sky.js';
import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';
import { EXRLoader } from 'three/addons/loaders/EXRLoader.js';
import { nextTick, onMounted, onBeforeUnmount } from 'vue';

// Container PBR: clean (manufactured < 1 year ago) vs rusty (older or no date)
const CONTAINER_TEXTURE_CLEAN = '/textures/container/metal_plate_4k/textures';
const CONTAINER_TEXTURE_RUSTY = '/textures/container/rusty_metal_grid_4k/textures';
const ONE_YEAR_MS = 365 * 24 * 60 * 60 * 1000;

function useRustyTextures(container) {
    const manufactured = container?.manufacture_date;
    if (!manufactured) return true;
    const t = new Date(manufactured).getTime();
    return Date.now() - t >= ONE_YEAR_MS;
}

const CONTAINER_COLORS = {
    standard: 0x0167b1,
    high_cube: 0x0d9488,
    refrigerated: 0x0284c7,
    flat_rack: 0x64748b,
};

export function useContainer3DScene(containerRef, container, containerState) {
    const state = containerState || {};
    let scene;
    let camera;
    let renderer;
    let orbitControls;
    let animationId;
    let raycaster;
    let pointer;
    let clickables = [];

    // Main lamp (ceiling inside)
    let mainLampMesh;
    let mainLampLight;

    // IR lamp
    let irLampMesh;
    let irLampLight;

    // AC
    let acGroup;
    let acFanMesh;
    let acLedMesh;

    // Air freshener
    let freshenerGroup;
    let sprayParticles;
    let sprayStartTime = 0;
    const SPRAY_DURATION = 800;

    // Drain pump (floor)
    let pumpGroup;
    let pumpImpellerMesh;

    // Fire sprinkler (ceiling)
    let sprinklerGroup;
    let sprinklerParticles;
    let sprinklerStartTime = 0;
    const SPRINKLER_DURATION = 2000;

    // Humidifier (wall)
    let humidifierGroup;
    let humidifierMistParticles;

    // Heater (wall)
    let heaterGroup;
    let heaterCoilMesh;

    // Ventilation (ceiling)
    let ventilationGroup;
    let ventilationFanMesh;

    // Smoke detector (ceiling)
    let smokeDetectorGroup;
    let smokeDetectorLedMesh;

    // Cargo door (+Z opening)
    let doorGroup;
    let doorCurrentAngle = 0;
    const DOOR_OPEN_ANGLE = Math.PI * 0.55;

    function getDimensions() {
        const w = Number(container?.width) || 2.44;
        const l = Number(container?.length) || 6.06;
        const h = Number(container?.height) || 2.59;
        return { width: w, length: l, height: h };
    }

    function getScale() {
        const { width, length, height } = getDimensions();
        const maxDim = Math.max(width, length, height);
        return 4 / maxDim;
    }

    function getScaledDims() {
        const dims = getDimensions();
        const s = getScale();
        return {
            width: dims.width * s,
            length: dims.length * s,
            height: dims.height * s,
        };
    }

    function init() {
        const el = containerRef?.value;
        if (!el) return;

        let w = el.clientWidth || el.offsetWidth || window.innerWidth;
        let h = el.clientHeight || el.offsetHeight || window.innerHeight;
        if (w < 10 || h < 10) {
            setTimeout(init, 100);
            return;
        }
        w = Math.max(w, 320);
        h = Math.max(h, 240);

        const dims = getScaledDims();
        const cw = dims.width;
        const ch = dims.height;
        const cl = dims.length;

        scene = new Scene();
        // Sky + beach + sea background (sunny day)
        const sky = new Sky();
        sky.scale.setScalar(500);
        scene.add(sky);
        // Sun: lower elevation for warm golden light (e.g. 28°), azimuth 200° for afternoon feel
        const sunPosition = new Vector3().setFromSphericalCoords(
            1,
            MathUtils.degToRad(90 - 28),
            MathUtils.degToRad(200)
        );
        sky.material.uniforms.sunPosition.value.copy(sunPosition);
        sky.material.uniforms.turbidity.value = 6;
        sky.material.uniforms.rayleigh.value = 0.45;
        sky.material.uniforms.mieCoefficient.value = 0.008;

        // Beach strip (sand) — in front of / under the view, slightly above sea
        const beachGeom = new PlaneGeometry(100, 50);
        const beachMat = new MeshStandardMaterial({
            color: 0xd4b896,
            metalness: 0.08,
            roughness: 0.92,
            transparent: false,
            depthWrite: true,
        });
        const beach = new Mesh(beachGeom, beachMat);
        beach.rotation.x = -Math.PI / 2;
        beach.position.set(0, -2.82, 25);
        scene.add(beach);

        // Sea — lighter blue-green, more reflective
        const seaGeom = new PlaneGeometry(150, 150);
        const seaMat = new MeshStandardMaterial({
            color: 0x1a6b8a,
            metalness: 0.22,
            roughness: 0.5,
            transparent: false,
            depthWrite: true,
        });
        const sea = new Mesh(seaGeom, seaMat);
        sea.rotation.x = -Math.PI / 2;
        sea.position.y = -3;
        scene.add(sea);

        camera = new PerspectiveCamera(50, w / h, 0.1, 1000);
        camera.position.set(6, 5, 6);
        camera.lookAt(0, 0, 0);

        renderer = new WebGLRenderer({ antialias: true });
        renderer.setSize(w, h);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.domElement.style.display = 'block';
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        try {
            renderer.outputColorSpace = 'srgb';
        } catch (_) {}
        el.appendChild(renderer.domElement);

        // HDR full scene (sky + ground + mountains) as background and env (public/hdri/newt1.hdr)
        const pmremGenerator = new PMREMGenerator(renderer);
        new RGBELoader()
            .loadAsync('/hdri/newt1.hdr')
            .then((texture) => {
                texture.mapping = EquirectangularReflectionMapping;
                scene.background = texture;
                const rt = pmremGenerator.fromEquirectangular(texture);
                scene.environment = rt.texture;
                pmremGenerator.dispose();
                if (sky) sky.visible = false;
                if (beach) beach.visible = false;
                if (sea) sea.visible = false;
            })
            .catch(() => {
                pmremGenerator.dispose();
            });

        orbitControls = new OrbitControls(camera, renderer.domElement);
        orbitControls.enableDamping = true;
        orbitControls.dampingFactor = 0.05;
        orbitControls.minDistance = 2;
        orbitControls.maxDistance = 20;
        orbitControls.minPolarAngle = 0.1;
        orbitControls.maxPolarAngle = Math.PI / 2 - 0.05;

        scene.add(new AmbientLight(0xffffff, 0.45));
        const dir1 = new DirectionalLight(0xffffff, 1.0);
        dir1.position.set(5, 10, 5);
        dir1.castShadow = true;
        dir1.shadow.mapSize.width = 1024;
        dir1.shadow.mapSize.height = 1024;
        scene.add(dir1);
        scene.add(new DirectionalLight(0xffffff, 0.35).position.set(-3, 5, 3));

        const containerColor = CONTAINER_COLORS[container?.type] || CONTAINER_COLORS.standard;
        const wallMat = new MeshStandardMaterial({
            color: containerColor,
            metalness: 0.55,
            roughness: 0.45,
            transparent: false,
            depthWrite: true,
            envMapIntensity: 0.75,
        });
        const metalMat = new MeshStandardMaterial({
            color: 0x6b7280,
            metalness: 0.8,
            roughness: 0.35,
            transparent: false,
            depthWrite: true,
            envMapIntensity: 0.85,
        });
        const edgeThick = 0.06;

        // Back, left, right, bottom, top — no front (open at z = cl/2)
        const back = new Mesh(new PlaneGeometry(cw, ch), wallMat);
        back.position.set(0, ch / 2, -cl / 2);
        back.receiveShadow = true;
        scene.add(back);
        const left = new Mesh(new PlaneGeometry(cl, ch), wallMat);
        left.position.set(-cw / 2, ch / 2, 0);
        left.rotation.y = Math.PI / 2;
        left.receiveShadow = true;
        scene.add(left);
        const right = new Mesh(new PlaneGeometry(cl, ch), wallMat);
        right.position.set(cw / 2, ch / 2, 0);
        right.rotation.y = -Math.PI / 2;
        right.receiveShadow = true;
        scene.add(right);
        const bottomMat = new MeshStandardMaterial({
            color: containerColor,
            metalness: 0.55,
            roughness: 0.45,
            transparent: false,
            depthWrite: true,
            envMapIntensity: 0.75,
        });
        const bottom = new Mesh(new PlaneGeometry(cw, cl), bottomMat);
        bottom.position.set(0, 0, 0);
        bottom.rotation.x = -Math.PI / 2;
        bottom.receiveShadow = true;
        scene.add(bottom);
        const topMat = new MeshStandardMaterial({
            color: containerColor,
            metalness: 0.55,
            roughness: 0.45,
            transparent: false,
            depthWrite: true,
            envMapIntensity: 0.75,
        });
        const top = new Mesh(new PlaneGeometry(cw, cl), topMat);
        top.position.set(0, ch, 0);
        top.rotation.x = Math.PI / 2;
        scene.add(top);

        // Interior floor — thick slab (not see-through)
        const floorMat = new MeshStandardMaterial({
            color: 0x2d3748,
            metalness: 0.55,
            roughness: 0.38,
            transparent: false,
            depthWrite: true,
            envMapIntensity: 0.7,
        });
        const floorThickness = 0.06;
        const floor = new Mesh(
            new BoxGeometry(cw * 0.98, floorThickness, cl * 0.98),
            floorMat
        );
        floor.position.set(0, floorThickness / 2 + 0.01, 0);
        floor.receiveShadow = true;
        scene.add(floor);

        // Ceiling layer under roof (solid, not see-through from inside)
        const ceilingMat = new MeshStandardMaterial({
            color: 0x374151,
            metalness: 0.45,
            roughness: 0.5,
            transparent: false,
            depthWrite: true,
            envMapIntensity: 0.6,
        });
        const ceilingThickness = 0.04;
        const ceilingSlab = new Mesh(
            new BoxGeometry(cw * 0.99, ceilingThickness, cl * 0.99),
            ceilingMat
        );
        ceilingSlab.position.set(0, ch - ceilingThickness / 2 - 0.01, 0);
        ceilingSlab.receiveShadow = true;
        scene.add(ceilingSlab);

        // Container PBR textures: clean (serviced < 1y) or rusty (serviced >= 1y / never)
        const isRusty = useRustyTextures(container);
        const textureBase = isRusty ? CONTAINER_TEXTURE_RUSTY : CONTAINER_TEXTURE_CLEAN;
        const tintHex = CONTAINER_COLORS[container?.type] ?? CONTAINER_COLORS.standard;
        const texLoader = new TextureLoader();
        const exrLoader = new EXRLoader();
        const containerMats = [wallMat, bottomMat, topMat, floorMat, ceilingMat];

        const diffFile = isRusty ? 'rusty_metal_grid_diff_4k.jpg' : 'metal_plate_diff_4k.jpg';
        const aoFile = isRusty ? 'rusty_metal_grid_ao_4k.jpg' : 'metal_plate_ao_4k.jpg';
        const norFile = isRusty ? 'rusty_metal_grid_nor_gl_4k.exr' : 'metal_plate_nor_gl_4k.exr';
        const roughFile = isRusty ? 'rusty_metal_grid_rough_4k.exr' : 'metal_plate_rough_4k.exr';

        texLoader.load(
            `${textureBase}/${diffFile}`,
            (colorMap) => {
                containerMats.forEach((m) => {
                    m.map = colorMap;
                    m.color.setHex(tintHex);
                });
            },
            undefined,
            () => {}
        );
        texLoader.load(
            `${textureBase}/${aoFile}`,
            (aoMap) => {
                containerMats.forEach((m) => {
                    m.aoMap = aoMap;
                    m.aoMapIntensity = 1;
                });
            },
            undefined,
            () => {}
        );
        exrLoader.load(
            `${textureBase}/${norFile}`,
            (normalMap) => {
                containerMats.forEach((m) => {
                    m.normalMap = normalMap;
                    m.normalScale.setScalar(1);
                });
            },
            undefined,
            () => {}
        );
        exrLoader.load(
            `${textureBase}/${roughFile}`,
            (roughnessMap) => {
                containerMats.forEach((m) => {
                    m.roughnessMap = roughnessMap;
                    m.roughness = 1;
                });
            },
            undefined,
            () => {}
        );
        if (!isRusty) {
            exrLoader.load(
                `${textureBase}/metal_plate_metal_4k.exr`,
                (metalnessMap) => {
                    containerMats.forEach((m) => {
                        m.metalnessMap = metalnessMap;
                        m.metalness = 1;
                    });
                },
                undefined,
                () => {}
            );
        } else {
            containerMats.forEach((m) => {
                m.metalness = 0.5;
                m.metalnessMap = null;
            });
        }

        const fz = cl / 2 + edgeThick / 2;
        const frameLeft = new Mesh(new BoxGeometry(edgeThick, ch + edgeThick * 2, edgeThick), metalMat);
        frameLeft.position.set(-cw / 2 - edgeThick / 2, ch / 2, fz);
        scene.add(frameLeft);
        const frameRight = new Mesh(new BoxGeometry(edgeThick, ch + edgeThick * 2, edgeThick), metalMat);
        frameRight.position.set(cw / 2 + edgeThick / 2, ch / 2, fz);
        scene.add(frameRight);
        const frameTop = new Mesh(new BoxGeometry(cw + edgeThick * 2, edgeThick, edgeThick), metalMat);
        frameTop.position.set(0, ch + edgeThick / 2, fz);
        scene.add(frameTop);
        const frameBottom = new Mesh(new BoxGeometry(cw + edgeThick * 2, edgeThick, edgeThick), metalMat);
        frameBottom.position.set(0, -edgeThick / 2, fz);
        scene.add(frameBottom);

        doorGroup = new Group();
        doorGroup.position.set(-cw / 2 + edgeThick * 0.55, ch / 2, cl / 2 + 0.03);
        const doorW = cw * 0.88;
        const doorH = ch * 0.88;
        const doorTh = 0.045;
        const doorSlab = new Mesh(
            new BoxGeometry(doorW, doorH, doorTh),
            metalMat.clone()
        );
        doorSlab.position.set(doorW / 2 - edgeThick * 0.2, 0, 0);
        doorSlab.userData.id = 'door';
        doorGroup.add(doorSlab);
        scene.add(doorGroup);
        clickables.push(doorSlab);

        const cornerGuard = (x, z) => {
            const g1 = new Mesh(new BoxGeometry(edgeThick * 1.5, ch + edgeThick, edgeThick), metalMat);
            g1.position.set(x, ch / 2, z);
            scene.add(g1);
            const g2 = new Mesh(new BoxGeometry(edgeThick, ch + edgeThick, edgeThick * 1.5), metalMat);
            g2.position.set(x, ch / 2, z);
            scene.add(g2);
        };
        cornerGuard(-cw / 2, -cl / 2);
        cornerGuard(cw / 2, -cl / 2);
        cornerGuard(cw / 2, cl / 2);
        cornerGuard(-cw / 2, cl / 2);

        // --- Main lamp (ceiling, center inside) ---
        const mainLampY = ch - 0.1;
        const mainLampGroup = new Group();
        mainLampGroup.position.set(0, mainLampY, 0);

        const bulbRadius = 0.12;
        const bulbGeom = new SphereGeometry(bulbRadius, 20, 16);
        const bulbMat = new MeshStandardMaterial({
            color: 0xfff8e7,
            emissive: 0x000000,
            emissiveIntensity: 0,
            roughness: 0.08,
            metalness: 0.02,
            envMapIntensity: 0.4,
        });
        mainLampMesh = new Mesh(bulbGeom, bulbMat);
        mainLampMesh.userData.id = 'mainLight';
        mainLampGroup.add(mainLampMesh);
        scene.add(mainLampGroup);

        mainLampLight = new PointLight(0xffe4a0, 0, 8);
        mainLampLight.position.set(0, mainLampY, 0);
        mainLampLight.castShadow = true;
        scene.add(mainLampLight);

        clickables.push(mainLampMesh);

        // --- IR lamp (back-left corner, slightly down from ceiling) ---
        const irX = -cw / 2 + 0.25;
        const irZ = -cl / 2 + 0.25;
        const irY = ch - 0.2;
        const irGeom = new SphereGeometry(0.08, 16, 12);
        const irMat = new MeshStandardMaterial({
            color: 0x990000,
            emissive: 0xcc0000,
            emissiveIntensity: 0,
            metalness: 0.3,
            roughness: 0.5,
            envMapIntensity: 0.5,
        });
        irLampMesh = new Mesh(irGeom, irMat);
        irLampMesh.position.set(irX, irY, irZ);
        irLampMesh.userData.id = 'irLamp';
        scene.add(irLampMesh);

        irLampLight = new PointLight(0xcc0000, 0, 4);
        irLampLight.position.set(irX, irY, irZ);
        scene.add(irLampLight);

        clickables.push(irLampMesh);

        // --- AC (back wall, mid-height) ---
        const acZ = -cl / 2 + 0.15;
        const acY = ch / 2;
        acGroup = new Group();
        acGroup.position.set(0, acY, acZ);

        const acPanel = new Mesh(
            new BoxGeometry(0.4, 0.25, 0.08),
            new MeshStandardMaterial({ color: 0x374151, metalness: 0.25, roughness: 0.6, envMapIntensity: 0.5 })
        );
        acPanel.position.z = -0.04;
        acGroup.add(acPanel);

        const fanGeom = new CircleGeometry(0.12, 16);
        const fanMat = new MeshStandardMaterial({ color: 0x6b7280, metalness: 0.6, roughness: 0.35, envMapIntensity: 0.75 });
        acFanMesh = new Mesh(fanGeom, fanMat);
        acFanMesh.position.z = 0.02;
        acFanMesh.rotation.x = -Math.PI / 2;
        acGroup.add(acFanMesh);

        const ledGeom = new PlaneGeometry(0.04, 0.02);
        const ledMat = new MeshStandardMaterial({
            color: 0x22c55e,
            emissive: 0x22c55e,
            emissiveIntensity: 0,
        });
        acLedMesh = new Mesh(ledGeom, ledMat);
        acLedMesh.position.set(0.15, 0.08, 0.02);
        acLedMesh.rotation.x = -Math.PI / 2;
        acGroup.add(acLedMesh);

        acPanel.userData.id = 'ac';
        acFanMesh.userData.id = 'ac';
        acLedMesh.userData.id = 'ac';
        clickables.push(acPanel, acFanMesh, acLedMesh);

        scene.add(acGroup);

        // --- Air freshener (opposite wall, mid-height) ---
        const freshZ = cl / 2 - 0.15;
        freshenerGroup = new Group();
        freshenerGroup.position.set(cw / 2 - 0.3, ch / 2, freshZ);
        freshenerGroup.rotation.y = -Math.PI / 2;

        const freshBox = new Mesh(
            new BoxGeometry(0.15, 0.2, 0.08),
            new MeshStandardMaterial({ color: 0x4b5563, metalness: 0.15, roughness: 0.65, envMapIntensity: 0.5 })
        );
        freshBox.userData.id = 'freshener';
        freshenerGroup.add(freshBox);
        scene.add(freshenerGroup);
        clickables.push(freshBox);

        // Spray particles (one-shot, created on trigger)
        const sprayGeom = new BufferGeometry();
        const sprayCount = 24;
        const sprayPositions = new Float32BufferAttribute(sprayCount * 3, 3);
        sprayGeom.setAttribute('position', sprayPositions);
        sprayParticles = new Points(
            sprayGeom,
            new PointsMaterial({
                color: 0xa5b4fc,
                size: 0.04,
                transparent: true,
                opacity: 0.9,
            })
        );
        sprayParticles.visible = false;
        freshenerGroup.add(sprayParticles);

        // --- Drain pump (floor, left corner) ---
        const pumpX = -cw / 2 + 0.4;
        const pumpZ = -cl / 2 + 0.4;
        const pumpY = 0.18;
        pumpGroup = new Group();
        pumpGroup.position.set(pumpX, pumpY, pumpZ);

        const pumpBase = new Mesh(
            new CylinderGeometry(0.12, 0.14, 0.08, 20),
            new MeshStandardMaterial({ color: 0x374151, metalness: 0.7, roughness: 0.35, envMapIntensity: 0.8 })
        );
        pumpBase.position.y = 0.04;
        pumpGroup.add(pumpBase);

        const impellerGeom = new CircleGeometry(0.1, 8);
        const impellerMat = new MeshStandardMaterial({ color: 0x6b7280, metalness: 0.65, roughness: 0.4, envMapIntensity: 0.75 });
        pumpImpellerMesh = new Mesh(impellerGeom, impellerMat);
        pumpImpellerMesh.position.y = 0.09;
        pumpImpellerMesh.rotation.x = -Math.PI / 2;
        pumpImpellerMesh.userData.id = 'pump';
        pumpGroup.add(pumpImpellerMesh);

        const pumpInlet = new Mesh(
            new CylinderGeometry(0.03, 0.04, 0.06, 12),
            new MeshStandardMaterial({ color: 0x4b5563, metalness: 0.6, roughness: 0.4, envMapIntensity: 0.7 })
        );
        pumpInlet.position.set(0.08, 0.02, 0);
        pumpInlet.rotation.z = Math.PI / 2;
        pumpInlet.userData.id = 'pump';
        pumpGroup.add(pumpInlet);
        pumpBase.userData.id = 'pump';
        scene.add(pumpGroup);
        clickables.push(pumpBase, pumpImpellerMesh, pumpInlet);

        // --- Fire sprinkler (ceiling, right side) ---
        const sprinklerX = cw / 2 - 0.35;
        const sprinklerZ = 0;
        const sprinklerY = ch - 0.06;
        sprinklerGroup = new Group();
        sprinklerGroup.position.set(sprinklerX, sprinklerY, sprinklerZ);

        const sprinklerNozzle = new Mesh(
            new CylinderGeometry(0.04, 0.06, 0.05, 12),
            new MeshStandardMaterial({ color: 0xef4444, metalness: 0.65, roughness: 0.28, envMapIntensity: 0.75 })
        );
        sprinklerNozzle.rotation.x = Math.PI / 2;
        sprinklerNozzle.userData.id = 'fireSprinkler';
        sprinklerGroup.add(sprinklerNozzle);

        const sprinklerCap = new Mesh(
            new CylinderGeometry(0.025, 0.025, 0.02, 12),
            new MeshStandardMaterial({ color: 0xdc2626, metalness: 0.5, roughness: 0.45, envMapIntensity: 0.6 })
        );
        sprinklerCap.position.y = -0.04;
        sprinklerCap.rotation.x = Math.PI / 2;
        sprinklerCap.userData.id = 'fireSprinkler';
        sprinklerGroup.add(sprinklerCap);

        const sprinklerGeom = new BufferGeometry();
        const sprinklerCount = 40;
        const sprinklerPositions = new Float32BufferAttribute(sprinklerCount * 3, 3);
        sprinklerGeom.setAttribute('position', sprinklerPositions);
        sprinklerParticles = new Points(
            sprinklerGeom,
            new PointsMaterial({
                color: 0x93c5fd,
                size: 0.06,
                transparent: true,
                opacity: 0.85,
            })
        );
        sprinklerParticles.visible = false;
        sprinklerParticles.position.y = -0.2;
        sprinklerGroup.add(sprinklerParticles);
        scene.add(sprinklerGroup);
        clickables.push(sprinklerNozzle, sprinklerCap);

        // --- Humidifier (left wall, mid) ---
        const humidX = -cw / 2 + 0.1;
        const humidZ = 0.25;
        const humidY = ch / 2 - 0.25;
        humidifierGroup = new Group();
        humidifierGroup.position.set(humidX, humidY, humidZ);
        humidifierGroup.rotation.y = Math.PI / 2;

        const humidBox = new Mesh(
            new BoxGeometry(0.2, 0.25, 0.12),
            new MeshStandardMaterial({ color: 0x5b6b7a, metalness: 0.2, roughness: 0.65, envMapIntensity: 0.5 })
        );
        humidBox.userData.id = 'humidifier';
        humidifierGroup.add(humidBox);

        const humidMistGeom = new BufferGeometry();
        const humidMistCount = 16;
        const humidMistPositions = new Float32BufferAttribute(humidMistCount * 3, 3);
        humidMistGeom.setAttribute('position', humidMistPositions);
        humidifierMistParticles = new Points(
            humidMistGeom,
            new PointsMaterial({
                color: 0xe0f2fe,
                size: 0.05,
                transparent: true,
                opacity: 0.6,
            })
        );
        humidifierMistParticles.visible = false;
        humidifierMistParticles.position.set(0.08, 0, 0);
        humidifierGroup.add(humidifierMistParticles);
        scene.add(humidifierGroup);
        clickables.push(humidBox);

        // --- Heater (left wall, lower) ---
        const heaterX = -cw / 2 + 0.1;
        const heaterZ = -0.2;
        const heaterY = ch / 3;
        heaterGroup = new Group();
        heaterGroup.position.set(heaterX, heaterY, heaterZ);
        heaterGroup.rotation.y = Math.PI / 2;

        const heaterBox = new Mesh(
            new BoxGeometry(0.35, 0.18, 0.1),
            new MeshStandardMaterial({ color: 0x374151, metalness: 0.45, roughness: 0.48, envMapIntensity: 0.6 })
        );
        heaterBox.userData.id = 'heater';
        heaterGroup.add(heaterBox);

        const coilGeom = new PlaneGeometry(0.25, 0.1);
        const coilMat = new MeshStandardMaterial({
            color: 0xff6b35,
            emissive: 0xff4500,
            emissiveIntensity: 0,
        });
        heaterCoilMesh = new Mesh(coilGeom, coilMat);
        heaterCoilMesh.position.set(0.06, 0, 0);
        heaterCoilMesh.rotation.x = -Math.PI / 2;
        heaterGroup.add(heaterCoilMesh);
        scene.add(heaterGroup);
        clickables.push(heaterBox);

        // --- Ventilation (ceiling, back-left area) ---
        const ventX = -0.35;
        const ventZ = -cl / 2 + 0.35;
        const ventY = ch - 0.08;
        ventilationGroup = new Group();
        ventilationGroup.position.set(ventX, ventY, ventZ);

        const ventGrille = new Mesh(
            new BoxGeometry(0.25, 0.04, 0.25),
            new MeshStandardMaterial({ color: 0x4b5563, metalness: 0.5, roughness: 0.5, envMapIntensity: 0.7 })
        );
        ventGrille.userData.id = 'ventilation';
        ventilationGroup.add(ventGrille);

        const ventFanGeom = new CircleGeometry(0.1, 12);
        const ventFanMat = new MeshStandardMaterial({ color: 0x6b7280, metalness: 0.6, roughness: 0.35, envMapIntensity: 0.75 });
        ventilationFanMesh = new Mesh(ventFanGeom, ventFanMat);
        ventilationFanMesh.position.z = 0.03;
        ventilationFanMesh.rotation.x = -Math.PI / 2;
        ventilationFanMesh.userData.id = 'ventilation';
        ventilationGroup.add(ventilationFanMesh);
        scene.add(ventilationGroup);
        clickables.push(ventGrille, ventilationFanMesh);

        // --- Smoke detector (ceiling) ---
        const smokeX = -0.25;
        const smokeZ = cl / 2 - 0.35;
        const smokeY = ch - 0.05;
        smokeDetectorGroup = new Group();
        smokeDetectorGroup.position.set(smokeX, smokeY, smokeZ);

        const smokeBody = new Mesh(
            new CylinderGeometry(0.08, 0.09, 0.03, 24),
            new MeshStandardMaterial({ color: 0x1f2937, metalness: 0.25, roughness: 0.65, envMapIntensity: 0.5 })
        );
        smokeBody.userData.id = 'smokeDetector';
        smokeDetectorGroup.add(smokeBody);

        const smokeLedGeom = new CircleGeometry(0.02, 16);
        const smokeLedMat = new MeshStandardMaterial({
            color: 0xef4444,
            emissive: 0xef4444,
            emissiveIntensity: 0,
        });
        smokeDetectorLedMesh = new Mesh(smokeLedGeom, smokeLedMat);
        smokeDetectorLedMesh.position.y = 0.02;
        smokeDetectorLedMesh.rotation.x = -Math.PI / 2;
        smokeDetectorGroup.add(smokeDetectorLedMesh);
        scene.add(smokeDetectorGroup);
        clickables.push(smokeBody);

        raycaster = new Raycaster();
        pointer = new Vector2();

        const onPointerMove = (e) => {
            const rect = el.getBoundingClientRect();
            pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
        };
        const onClick = () => {
            raycaster.setFromCamera(pointer, camera);
            const hits = raycaster.intersectObjects(clickables);
            if (hits.length) {
                const id = hits[0].object.userData.id;
                if (id === 'mainLight') state.mainLight = !state.mainLight;
                else if (id === 'irLamp') state.irLamp = !state.irLamp;
                else if (id === 'ac') state.acStatus = !state.acStatus;
                else if (id === 'freshener') state.freshenerTrigger = (state.freshenerTrigger || 0) + 1;
                else if (id === 'pump') state.pump = !state.pump;
                else if (id === 'fireSprinkler') state.fireSprinklerTrigger = (state.fireSprinklerTrigger || 0) + 1;
                else if (id === 'humidifier') state.humidifier = !state.humidifier;
                else if (id === 'heater') state.heater = !state.heater;
                else if (id === 'ventilation') state.ventilation = !state.ventilation;
                else if (id === 'smokeDetector') state.smokeAlarm = !state.smokeAlarm;
                else if (id === 'door') state.doorOpen = !state.doorOpen;
            }
        };
        el.addEventListener('pointermove', onPointerMove);
        el.addEventListener('click', onClick);
        renderer._clickHandler = onClick;
        renderer._pointerHandler = onPointerMove;

        const onResize = () => {
            if (!el || !camera || !renderer) return;
            const w = el.clientWidth;
            const h = el.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        };
        window.addEventListener('resize', onResize);
        renderer._resizeHandler = onResize;

        let lastFreshenerTrigger = state.freshenerTrigger || 0;
        let lastFireSprinklerTrigger = state.fireSprinklerTrigger || 0;

        // To sync with Laravel: e.g. axios.post('/api/container-iot-state', containerState)
        // syncWithBackend();

        function animate(time) {
            animationId = requestAnimationFrame(animate);
            orbitControls.update();

            // State → scene
            const mainOn = !!state.mainLight;
            mainLampLight.intensity = mainOn ? 1.5 : 0;
            mainLampMesh.material.emissive.setHex(mainOn ? 0xffd966 : 0x000000);
            mainLampMesh.material.emissiveIntensity = mainOn ? 0.9 : 0;

            const irOn = !!state.irLamp;
            irLampMesh.material.emissiveIntensity = irOn ? 0.8 : 0;
            irLampLight.intensity = irOn ? 0.6 : 0;

            const acOn = !!state.acStatus;
            acFanMesh.rotation.z += acOn ? 0.15 : 0;
            acLedMesh.material.emissiveIntensity = acOn ? 0.6 : 0;

            if (doorGroup) {
                const doorTarget = state.doorOpen ? DOOR_OPEN_ANGLE : 0;
                doorCurrentAngle += (doorTarget - doorCurrentAngle) * 0.14;
                doorGroup.rotation.y = doorCurrentAngle;
            }

            if (state.freshenerTrigger !== lastFreshenerTrigger) {
                lastFreshenerTrigger = state.freshenerTrigger;
                sprayStartTime = time;
            }
            if (sprayStartTime > 0) {
                const t = (time - sprayStartTime) / SPRAY_DURATION;
                if (t < 1) {
                    sprayParticles.visible = true;
                    const pos = sprayParticles.geometry.attributes.position.array;
                    for (let i = 0; i < sprayCount; i++) {
                        pos[i * 3] = (Math.random() - 0.5) * 0.4;
                        pos[i * 3 + 1] = (Math.random() - 0.5) * 0.3 + t * 0.2;
                        pos[i * 3 + 2] = 0.05 + t * 0.15;
                    }
                    sprayParticles.geometry.attributes.position.needsUpdate = true;
                    sprayParticles.material.opacity = 1 - t;
                } else {
                    sprayParticles.visible = false;
                    sprayStartTime = 0;
                }
            }

            // Drain pump: impeller rotation
            if (pumpImpellerMesh) {
                if (state.pump) pumpImpellerMesh.rotation.z += 0.2;
            }

            // Fire sprinkler: one-shot water burst
            if (state.fireSprinklerTrigger !== lastFireSprinklerTrigger) {
                lastFireSprinklerTrigger = state.fireSprinklerTrigger;
                sprinklerStartTime = time;
            }
            if (sprinklerStartTime > 0 && sprinklerParticles) {
                const st = (time - sprinklerStartTime) / SPRINKLER_DURATION;
                if (st < 1) {
                    sprinklerParticles.visible = true;
                    const pos = sprinklerParticles.geometry.attributes.position.array;
                    for (let i = 0; i < sprinklerCount; i++) {
                        const r = 0.15 * Math.sqrt(st) + (Math.random() - 0.5) * 0.1;
                        const a = (i / sprinklerCount) * Math.PI * 2 + time * 0.002;
                        pos[i * 3] = Math.cos(a) * r;
                        pos[i * 3 + 1] = -0.3 * st - (Math.random() * 0.2);
                        pos[i * 3 + 2] = Math.sin(a) * r;
                    }
                    sprinklerParticles.geometry.attributes.position.needsUpdate = true;
                    sprinklerParticles.material.opacity = st < 0.8 ? 0.9 : 0.9 * (1 - (st - 0.8) / 0.2);
                } else {
                    sprinklerParticles.visible = false;
                    sprinklerStartTime = 0;
                }
            }

            // Humidifier: gentle mist when on
            if (humidifierMistParticles) {
                humidifierMistParticles.visible = !!state.humidifier;
                if (state.humidifier) {
                    const pos = humidifierMistParticles.geometry.attributes.position.array;
                    for (let i = 0; i < humidMistCount; i++) {
                        pos[i * 3] = (Math.random() - 0.5) * 0.15;
                        pos[i * 3 + 1] = (Math.random() - 0.5) * 0.2 + Math.sin(time * 0.002 + i) * 0.05;
                        pos[i * 3 + 2] = 0.02 + (time * 0.0003 + i * 0.02) % 0.15;
                    }
                    humidifierMistParticles.geometry.attributes.position.needsUpdate = true;
                }
            }

            // Heater: coil glow
            if (heaterCoilMesh) {
                heaterCoilMesh.material.emissiveIntensity = state.heater ? 0.7 : 0;
            }

            // Ventilation: fan rotation
            if (ventilationFanMesh) {
                if (state.ventilation) ventilationFanMesh.rotation.z += 0.12;
            }

            // Smoke detector: LED
            if (smokeDetectorLedMesh) {
                smokeDetectorLedMesh.material.emissiveIntensity = state.smokeAlarm ? 0.9 : 0;
            }

            raycaster.setFromCamera(pointer, camera);
            const hits = raycaster.intersectObjects(clickables);
            renderer.domElement.style.cursor = hits.length ? 'pointer' : 'grab';

            renderer.render(scene, camera);
        }
        animate(performance.now());
    }

    onMounted(async () => {
        await nextTick();
        requestAnimationFrame(() => {
            init();
        });
    });

    onBeforeUnmount(() => {
        if (animationId) cancelAnimationFrame(animationId);
        if (orbitControls) orbitControls.dispose();
        if (renderer?._resizeHandler) window.removeEventListener('resize', renderer._resizeHandler);
        const el = containerRef?.value;
        if (el && renderer) {
            if (renderer._clickHandler) el.removeEventListener('click', renderer._clickHandler);
            if (renderer._pointerHandler) el.removeEventListener('pointermove', renderer._pointerHandler);
            if (renderer.domElement.parentNode === el) el.removeChild(renderer.domElement);
            renderer.dispose();
        }
        if (mainLampMesh?.geometry) mainLampMesh.geometry.dispose();
        if (mainLampMesh?.material) mainLampMesh.material.dispose();
        if (irLampMesh?.geometry) irLampMesh.geometry.dispose();
        if (irLampMesh?.material) irLampMesh.material.dispose();
        if (sprayParticles?.geometry) sprayParticles.geometry.dispose();
        if (sprayParticles?.material) sprayParticles.material.dispose();
        if (pumpImpellerMesh?.geometry) pumpImpellerMesh.geometry.dispose();
        if (pumpImpellerMesh?.material) pumpImpellerMesh.material.dispose();
        if (sprinklerParticles?.geometry) sprinklerParticles.geometry.dispose();
        if (sprinklerParticles?.material) sprinklerParticles.material.dispose();
        if (humidifierMistParticles?.geometry) humidifierMistParticles.geometry.dispose();
        if (humidifierMistParticles?.material) humidifierMistParticles.material.dispose();
        if (heaterCoilMesh?.geometry) heaterCoilMesh.geometry.dispose();
        if (heaterCoilMesh?.material) heaterCoilMesh.material.dispose();
        if (ventilationFanMesh?.geometry) ventilationFanMesh.geometry.dispose();
        if (ventilationFanMesh?.material) ventilationFanMesh.material.dispose();
        if (smokeDetectorLedMesh?.geometry) smokeDetectorLedMesh.geometry.dispose();
        if (smokeDetectorLedMesh?.material) smokeDetectorLedMesh.material.dispose();
    });
}

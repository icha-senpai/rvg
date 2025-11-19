/*
 * ue-starfield.js
 *
 * Version: 2.0.1
 * Description: Interactive starfield background
 * Customized version - Adarsh: Compatibility for multiple instances, Custom Initialization, HEX color to RGB conversion, Star Size, Star rounded border, performance optimization.
 *
 * Forked from starfield.js © 2020 AnnikaV9 (https://github.com/AnnikaV9/starfield.js)
 *
 */

(function (root, factory) {
  if (typeof define === "function" && define.amd) {
    define([], factory);
  } else if (typeof module === "object" && module.exports) {
    module.exports = factory();
  } else {
    root.Starfield = factory();
  }
})(this, function () {

  const instances = [];
  let globalAnimating = false;

  function startGlobalLoop() {
    if (globalAnimating) return;
    globalAnimating = true;

    let lastTimestamp = performance.now();

    function animate(timestamp) {
      const deltaTime = (timestamp - lastTimestamp) / 16.67;
      lastTimestamp = timestamp;

      for (const inst of instances) {
        inst._draw(deltaTime);
      }

      requestAnimationFrame(animate);
    }

    requestAnimationFrame(animate);
  }

  function createStarfield() {
    const instance = {};
    const config = {
      numStars: 250,
      baseSpeed: 1,
      trailLength: 0.8,
      starColor: "rgb(255, 255, 255)",
      canvasColor: "rgb(0, 0, 0)",
      hueJitter: 0,
      maxAcceleration: 10,
      accelerationRate: 0.2,
      decelerationRate: 0.2,
      minSpawnRadius: 80,
      maxSpawnRadius: 500,
      auto: true,
      originX: null,
      originY: null,
      container: null,
      originElement: null,
    };

    let stars = [];
    let accelerate = false;
    let accelerationFactor = 0;
    let originX = 0;
    let originY = 0;
    let prevOriginX = 0;
    let prevOriginY = 0;
    let canvas, ctx;
    let width, height;
    let canvasRGB = [0, 0, 0];
    let lastCanvasColor = config.canvasColor;
    let origin, container;

    const mouseEnterHandler = () => (accelerate = true);
    const mouseLeaveHandler = () => (accelerate = false);
    const resizeHandler = () => windowResized(container, origin);

    function visibilityHandler() {
      if (document.visibilityState === "visible") {
        // handled by global loop
      }
    }

    function getOriginY(origin, container) {
      const originRect = origin.getBoundingClientRect();
      const containerRect = container.getBoundingClientRect();
      return originRect.top - containerRect.top + originRect.height / 2;
    }

    function getOriginX(origin, container) {
      const originRect = origin.getBoundingClientRect();
      const containerRect = container.getBoundingClientRect();
      return originRect.left - containerRect.left + originRect.width / 2;
    }

    function setup(userConfig = {}) {
      Object.assign(config, userConfig);

      container = config.container || document.querySelector(".starfield");
      if (!container) throw new Error("Starfield: No container element found.");
      container.style.position = "relative";

      width = container.clientWidth;
      height = container.clientHeight;

      canvas = document.createElement("canvas");
      canvas.width = width;
      canvas.height = height;
      canvas.style.position = "absolute";
      canvas.style.top = "0";
      canvas.style.left = "0";
      canvas.style.width = "100%";
      canvas.style.height = "100%";
      // canvas.style.zIndex = "-1";

      canvasRGB = parseRGBA(config.canvasColor);
      container.appendChild(canvas);
      ctx = canvas.getContext("2d");

      if (config.auto) {
        origin = config.originElement || document.querySelector(".starfield-origin");
        if (!origin) throw new Error("Starfield: No origin element found.");
        originX = getOriginX(origin, container);
        originY = getOriginY(origin, container);
        origin.addEventListener("mouseenter", mouseEnterHandler);
        origin.addEventListener("mouseleave", mouseLeaveHandler);
        window.addEventListener("resize", resizeHandler);
      } else {
        originX = config.originX !== null ? config.originX : width / 2;
        originY = config.originY !== null ? config.originY : height / 2;
      }

      for (let i = 0; i < config.numStars; i++) {
        const star = createRandomStar();
        stars.push(star);
      }

      document.addEventListener("visibilitychange", visibilityHandler);

      if (!instances.includes(instance)) instances.push(instance);
      startGlobalLoop();
    }

    function windowResized(container, origin) {
      width = container.clientWidth;
      height = container.clientHeight;
      canvas.width = width;
      canvas.height = height;
      originX = getOriginX(origin, container);
      originY = getOriginY(origin, container);
      stars.forEach(star => star.reset());
    }

    function createRandomStar() {
      const angle = random(0, Math.PI * 2);
      const radius = random(config.minSpawnRadius, config.maxSpawnRadius);
      const x = originX + Math.cos(angle) * radius;
      const y = originY + Math.sin(angle) * radius;
      return new Star(x, y);
    }

    class Star {
      constructor(x, y) {
        this.pos = { x, y };
        this.prevpos = { x, y };
        this.vel = { x: 0, y: 0 };
        this.angle = Math.atan2(y - originY, x - originX);
        this.baseSpeed = random(config.baseSpeed * 0.5, config.baseSpeed * 1.5);
        this.hueOffset = random(-config.hueJitter, config.hueJitter);
      }

      reset() {
        const newStar = createRandomStar();
        this.pos.x = newStar.pos.x;
        this.pos.y = newStar.pos.y;
        this.prevpos.x = this.pos.x;
        this.prevpos.y = this.pos.y;
        this.vel.x = 0;
        this.vel.y = 0;
        this.angle = Math.atan2(this.pos.y - originY, this.pos.x - originX);
        this.baseSpeed = random(config.baseSpeed * 0.5, config.baseSpeed * 1.5);
        this.hueOffset = random(-config.hueJitter, config.hueJitter);
      }

      update(acc, deltaTime) {
        const adjustedAcc = acc * this.baseSpeed;
        this.vel.x += Math.cos(this.angle) * adjustedAcc * deltaTime;
        this.vel.y += Math.sin(this.angle) * adjustedAcc * deltaTime;
        this.prevpos.x = this.pos.x;
        this.prevpos.y = this.pos.y;
        this.pos.x += this.vel.x * deltaTime;
        this.pos.y += this.vel.y * deltaTime;
      }

      draw() {
        const velMag = Math.sqrt(this.vel.x ** 2 + this.vel.y ** 2);
        const alpha = map(velMag, 0, 10, 0, 1);
        const weight = map(velMag, 0, 10, 1, 3) * config.starSize;
        ctx.lineWidth = weight;
        ctx.lineCap = "round";
        ctx.lineJoin = "round";
        const [r, g, b] = parseRGBA(config.starColor);
        const [h, s, l] = rgbToHsl(r, g, b);
        const adjustedH = (h + this.hueOffset + 360) % 360;
        const [newR, newG, newB] = hslToRgb(adjustedH, s, l).map(v => Math.round(v));
        ctx.strokeStyle = `rgba(${newR}, ${newG}, ${newB}, ${alpha})`;
        ctx.beginPath();
        ctx.moveTo(this.prevpos.x, this.prevpos.y);
        ctx.lineTo(this.pos.x, this.pos.y);
        ctx.stroke();
      }

      isActive() {
        return onScreen(this.pos.x, this.pos.y);
      }

      updateAngle() {
        this.angle = Math.atan2(this.pos.y - originY, this.pos.x - originX);
      }
    }

    instance._draw = function (deltaTime) {
      if (config.auto) {
        originX = getOriginX(origin, container);
        originY = getOriginY(origin, container);
        if (originX !== prevOriginX || originY !== prevOriginY) {
          stars.forEach(star => star.updateAngle());
          prevOriginX = originX;
          prevOriginY = originY;
        }
      }

      if (lastCanvasColor !== config.canvasColor) {
        canvasRGB = parseRGBA(config.canvasColor);
        lastCanvasColor = config.canvasColor;
      }

      const [bgR, bgG, bgB] = canvasRGB;
      ctx.fillStyle = `rgba(${bgR}, ${bgG}, ${bgB}, ${1 - config.trailLength})`;
      ctx.fillRect(0, 0, width, height);

      accelerationFactor = accelerate
        ? Math.min(accelerationFactor + config.accelerationRate * deltaTime, config.maxAcceleration)
        : Math.max(accelerationFactor - config.decelerationRate * deltaTime, 0);

      const baseAcc = 0.01;
      const currentAcc = baseAcc * (1 + accelerationFactor * 10);

      for (let star of stars) {
        star.update(currentAcc, deltaTime);
        star.draw();
        if (!star.isActive()) star.reset();
      }
    };

    function onScreen(x, y) {
      return x >= 0 && x <= width && y >= 0 && y <= height;
    }

    function random(min, max) {
      return Math.random() * (max - min) + min;
    }

    function rgbToHsl(r, g, b) {
      r /= 255; g /= 255; b /= 255;
      const max = Math.max(r, g, b), min = Math.min(r, g, b);
      let h, s, l = (max + min) / 2;
      if (max === min) { h = s = 0; }
      else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
          case r: h = ((g - b) / d + (g < b ? 6 : 0)); break;
          case g: h = ((b - r) / d + 2); break;
          case b: h = ((r - g) / d + 4); break;
        }
        h /= 6;
      }
      return [h * 360, s, l];
    }

    function hslToRgb(h, s, l) {
      let r, g, b;
      h /= 360;
      if (s === 0) { r = g = b = l; }
      else {
        const hue2rgb = (p, q, t) => {
          if (t < 0) t += 1;
          if (t > 1) t -= 1;
          if (t < 1 / 6) return p + (q - p) * 6 * t;
          if (t < 1 / 2) return q;
          if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
          return p;
        };
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
      }
      return [r * 255, g * 255, b * 255];
    }

    function parseRGBA(color) {
      if (typeof color !== 'string') throw new TypeError('Color must be a string');
      color = color.trim();
      if (/^rgba?\(/i.test(color)) {
        const nums = color.match(/[\d.]+/g).map(Number);
        return [nums[0], nums[1], nums[2]];
      }
      let hex = color.replace(/^0x/i, '').replace(/^#/, '');
      if (hex.length === 3 || hex.length === 4) hex = hex.split('').map(c => c + c).join('');
      if (hex.length !== 6 && hex.length !== 8) throw new Error('Invalid color format');
      const int = parseInt(hex, 16);
      const r = (int >> (hex.length === 8 ? 24 : 16)) & 255;
      const g = (int >> (hex.length === 8 ? 16 : 8)) & 255;
      const b = (int >> (hex.length === 8 ? 8 : 0)) & 255;
      return [r, g, b];
    }

    function map(value, start1, stop1, start2, stop2) {
      return ((value - start1) / (stop1 - start1)) * (stop2 - start2) + start2;
    }

    function setAccelerate(state) { accelerate = state; }
    function setOrigin(x, y) {
      originX = x; originY = y;
      stars.forEach(star => star.angle = Math.atan2(star.pos.y - originY, star.pos.x - originX));
    }

    function resize(newWidth, newHeight) {
      width = newWidth; height = newHeight;
      canvas.width = width; canvas.height = height;
      originY = config.originY !== null ? config.originY : height / 2;
      stars.forEach(star => star.reset());
    }

    function cleanup() {
      if (origin) {
        origin.removeEventListener("mouseenter", mouseEnterHandler);
        origin.removeEventListener("mouseleave", mouseLeaveHandler);
      }
      window.removeEventListener("resize", resizeHandler);
      document.removeEventListener("visibilitychange", visibilityHandler);
      if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
      const idx = instances.indexOf(instance);
      if (idx > -1) instances.splice(idx, 1);
      stars = [];
    }

    instance.setup = setup;
    instance.setAccelerate = setAccelerate;
    instance.setOrigin = setOrigin;
    instance.resize = resize;
    instance.cleanup = cleanup;
    instance.config = config;

    return instance;
  }

  return { create: createStarfield };
});

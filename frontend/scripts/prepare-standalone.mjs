import { cp } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const root = fileURLToPath(new URL("../", import.meta.url));
const standalone = path.join(root, ".next", "standalone");
await cp(path.join(root, "public"), path.join(standalone, "public"), {recursive: true});
await cp(path.join(root, ".next", "static"), path.join(standalone, ".next", "static"), {recursive: true});

const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("desktop", {
  cacheGet: (key) => ipcRenderer.invoke("desktop:cache-get", key),
  cachePut: (key, value) => ipcRenderer.invoke("desktop:cache-put", key, value),
  enqueue: (request) => ipcRenderer.invoke("desktop:enqueue", request),
  sync: (options) => ipcRenderer.invoke("desktop:sync", options),
});

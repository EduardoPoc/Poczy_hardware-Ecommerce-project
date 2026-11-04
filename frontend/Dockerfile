FROM node:24-alpine

WORKDIR /app

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

COPY --chown=${LOCAL_UID}:${LOCAL_GID} package*.json ./
RUN if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi \
    && chown -R "$LOCAL_UID:$LOCAL_GID" /app

COPY --chown=${LOCAL_UID}:${LOCAL_GID} . .

USER ${LOCAL_UID}:${LOCAL_GID}

EXPOSE 5173

CMD ["sh", "-c", "if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi && exec npm run dev -- --port 5173 --strictPort"]

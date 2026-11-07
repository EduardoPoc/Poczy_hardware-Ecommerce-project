FROM node:24-alpine AS dependencies
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

FROM dependencies AS development
ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
RUN chown -R "$LOCAL_UID:$LOCAL_GID" /app
COPY --chown=${LOCAL_UID}:${LOCAL_GID} . .
USER ${LOCAL_UID}:${LOCAL_GID}
EXPOSE 5173
CMD ["sh", "-c", "npm ci --no-audit --no-fund && exec npm run dev -- --port 5173 --strictPort"]

FROM dependencies AS build
ARG VITE_API_URL=""
ENV VITE_API_URL=${VITE_API_URL}
COPY . .
RUN npm test && npm run build

FROM nginx:stable-alpine AS production
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80

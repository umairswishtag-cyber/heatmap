FROM node:24-alpine AS tracker
WORKDIR /tracker
COPY packages/tracker/package.json packages/tracker/package-lock.json ./
RUN npm ci
COPY packages/tracker/build.js ./
COPY packages/tracker/src ./src
RUN npm run build

FROM nginx:1.29-alpine
COPY infrastructure/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=tracker /tracker/dist /srv/tracker

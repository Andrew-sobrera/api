import express from 'express';
import dotenv from 'dotenv';
import task from './src/routes/taskRoutes';

import './src/database';

dotenv.config();

class App {
  constructor() {
    this.app = express();
    this.middlewares();
    this.routes();
  }

  middlewares() {
    this.app.use(express.urlencoded({ extended: true }));
    this.app.use(express.json());
  }

  routes() {
    this.app.use('/tasks', task);
  }
}
export default new App().app;

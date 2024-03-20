import express from 'express';
import dotenv from 'dotenv';
import task from './src/routes/taskRoutes';
import user from './src/routes/userRoutes';

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
    this.app.use('/users', user);
  }
}
export default new App().app;

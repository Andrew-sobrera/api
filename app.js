import express from 'express';
import dotenv from 'dotenv';
import task from './src/routes/taskRoutes';
import user from './src/routes/userRoutes';
import uploader from './src/routes/uploaderRoutes';
import cors from 'cors'
import './src/database';
import LoginRequired from './src/middlewares/LoginRequired';

dotenv.config();

class App {
  constructor() {
    this.app = express();
    this.middlewares();
    this.routes();
  }
  

  middlewares() {
    this.app.use(cors());
    this.app.use(express.urlencoded({ extended: true }));
    this.app.use(express.json());
  }

  routes() {
    this.app.use(cors());
    this.app.use('/tasks', LoginRequired ,task);
    this.app.use('/users', user);
    this.app.use('/uploader', uploader)
  }
}
export default new App().app;

import { Router } from "express";
import multer from "multer";


import uploaderController from "../controllers/UploaderController";
import multerConfig from '../config/multerConfig';

const upload = multer(multerConfig)

const router = new Router

router.post('/', upload.single('image') ,uploaderController.store)

export default router;
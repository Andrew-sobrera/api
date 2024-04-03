import { S3Client, PutObjectCommand } from "@aws-sdk/client-s3";
import multer from "multer";
import multerS3 from "multer-s3";
import { extname } from "path";
import { v4 as uuidv4 } from "uuid";
import dotenv from "dotenv";

dotenv.config();

// Configuração do cliente S3
const s3Client = new S3Client({
  region: process.env.AWS_REGION,
  credentials: {
    accessKeyId: process.env.AWS_ACCESS_KEY_ID,
    secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY,
  },
});


const upload = multer({
  storage: multerS3Storage({
    s3: s3Client,
    bucket: "images-s3-api",
  }),
});

// Função para configurar o armazenamento no S3
function multerS3Storage(opts) {
  return multerS3({
    s3: opts.s3,
    bucket: opts.bucket,
    contentType: multerS3.AUTO_CONTENT_TYPE,
    key: (req, file, cb) => {
      cb(null, `${uuidv4()}${extname(file.originalname)}`);
    },
  });
}

export default upload;
